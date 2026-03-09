<script setup>
import { ref, reactive, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import Swal from 'sweetalert2'
import * as XLSX from 'xlsx'

const router = useRouter()

/* ===================== STATE ===================== */
const rows = ref([])
const parsing = ref(false)
const anakByNik = ref(new Map())
const importMode = ref('upsert')
const progress = reactive({ total: 0, done: 0 })
const importing = ref(false)
const fileInfo = ref(null) // { desa, tahun, bulan }

/* ===================== POSYANDU ===================== */
const posyanduList = ref([])
const posMap = ref(new Map())   // "DESA|NAMA" -> id
const loadingPosyandu = ref(false)

const norm = (s) =>
  String(s || '')
    .toUpperCase()
    .replace(/[^\p{L}\p{N}\s]/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim()

const makePosKey = (desa, nama) => `${norm(desa)}|${norm(nama)}`

async function loadPosyandu() {
  if (posMap.value.size) return
  loadingPosyandu.value = true
  try {
    const res = await api.get('/posyandu', { headers: { Accept: 'application/json' } })
    const arr = Array.isArray(res.data) ? res.data
      : Array.isArray(res?.data?.data) ? res.data.data
      : Array.isArray(res?.data?.data?.data) ? res.data.data.data : []
    posyanduList.value = arr
    const m = new Map()
    for (const p of arr) {
      if (p?.desa && p?.nama) m.set(makePosKey(p.desa, p.nama), p.id)
    }
    posMap.value = m
  } catch {
    posMap.value = new Map()
  } finally {
    loadingPosyandu.value = false
  }
}

function findPosyanduId(desaFromStart, namaPosyandu) {
  // Coba exact match dulu: desa dari Start sheet + nama dari kolom L
  const exactKey = makePosKey(desaFromStart, namaPosyandu)
  if (posMap.value.has(exactKey)) return posMap.value.get(exactKey)

  // Fallback: cari berdasarkan nama posyandu saja (ignore desa)
  const normNama = norm(namaPosyandu)
  for (const [key, id] of posMap.value.entries()) {
    const keyNama = key.split('|')[1] || ''
    if (keyNama === normNama) return id
  }
  return null
}

/* ===================== PRELOAD ANAK ===================== */
async function preloadAnak() {
  const res = await api.get('/anak', { headers: { Accept: 'application/json' } })
  const arr = Array.isArray(res.data) ? res.data
    : Array.isArray(res.data?.data) ? res.data.data : []
  const m = new Map()
  for (const a of arr) {
    if (a?.nik) m.set(String(a.nik).replace(/\D/g, ''), a)
  }
  anakByNik.value = m
}

/* =====================================================================
   FIXED-COLUMN MAPPING (format baku aplikasi ini):
   
   Col A(1)  : Kode Provinsi       → skip
   Col B(2)  : Kode Kab/Kota       → skip
   Col C(3)  : Kode Kecamatan      → skip
   Col D(4)  : Anak Ke             → anak_ke
   Col E(5)  : BB Lahir (kg)       → berat_lahir
   Col F(6)  : Panjang Lahir (cm)  → panjang_lahir
   Col G(7)  : NIK Anak            → nik
   Col H(8)  : Buku KIA (1=ya)     → kia
   Col I(9)  : Nama Ortu           → nama_ortu
   Col J(10) : RT                  → rt
   Col K(11) : RW                  → rw
   Col L(12) : Nama Posyandu       → posyandu_id (via lookup)
   Col M(13) : No urut             → skip
   Col N(14) : Nama Anak           → nama_anak
   Col O(15) : L/P (1=L, 2=P)     → jenis_kelamin
   Col P(16) : Tanggal Lahir (DD)  → tgl_lahir DD
   Col Q(17) : Bulan Lahir         → tgl_lahir MM
   Col R(18) : Tahun Lahir (2-dig) → tgl_lahir YYYY
   ===================================================================== */
const COL = {
  anak_ke:       4,   // D
  berat_lahir:   5,   // E
  panjang_lahir: 6,   // F
  nik:           7,   // G
  kia:           8,   // H
  nama_ortu:     9,   // I
  rt:           10,   // J
  rw:           11,   // K
  posyandu_nm:  12,   // L
  nama_anak:    14,   // N
  jenis_kelamin:15,   // O
  tgl_dd:       16,   // P
  tgl_mm:       17,   // Q
  tgl_yy:       18,   // R
}

/* ===================== HELPERS ===================== */
function cellVal(ws, row, col) {
  const v = ws.cell(row, col)
  if (!v) return null
  // Abaikan formula cells
  if (typeof v === 'string' && (v.startsWith('=') || v.startsWith('+'))) return null
  return v === '' ? null : v
}

function cleanNik(v) {
  return String(v || '').replace(/\xa0/g, '').replace(/\D/g, '')
}

function toJK(v) {
  const n = parseInt(v)
  if (n === 1) return 'L'
  if (n === 2) return 'P'
  const s = String(v || '').trim().toLowerCase()
  if (['l', 'laki', 'laki-laki', 'male', 'm'].includes(s)) return 'L'
  if (['p', 'perempuan', 'female', 'f', 'wanita'].includes(s)) return 'P'
  return ''
}

function toJKLabel(code) {
  if (code === 'L') return 'LAKI-LAKI'
  if (code === 'P') return 'PEREMPUAN'
  return '-'
}

function buildTglLahir(dd, mm, yy) {
  const d = parseInt(dd)
  const m = parseInt(mm)
  let y = parseInt(yy)
  if (!d || !m || !y) return ''
  // Tahun 2-digit: 00-99 → 2000-2099
  if (y < 100) y += 2000
  return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}

function toBool(v) {
  if (v === null || v === undefined) return false
  const n = parseInt(v)
  if (!isNaN(n)) return n === 1
  return ['y', 'ya', 'yes', 'true', '1'].includes(String(v).trim().toLowerCase())
}

/* ===================== PARSE SATU SHEET ===================== */
function parseSheet(sheetData, desaFromStart) {
  // sheetData: array of arrays (dari XLSX.utils.sheet_to_json header:1)
  // Baris data mulai dari index 8 (row Excel 9, index 0-based = 8)
  const DATA_START = 8  // index 0-based

  const result = []
  for (let i = DATA_START; i < sheetData.length; i++) {
    const row = sheetData[i]
    const excelRow = i + 1

    const rawNik = row[COL.nik - 1]
    const nik = cleanNik(rawNik)

    // Skip baris kosong / bukan data (NIK harus minimal 10 digit angka)
    if (!/^\d{10,}$/.test(nik)) continue

    const nama_anak = String(row[COL.nama_anak - 1] || '').trim()
    if (!nama_anak) continue

    const jkCode = toJK(row[COL.jenis_kelamin - 1])
    const tgl_lahir = buildTglLahir(
      row[COL.tgl_dd - 1],
      row[COL.tgl_mm - 1],
      row[COL.tgl_yy - 1]
    )

    const namaPosyandu = String(row[COL.posyandu_nm - 1] || '').trim()
    const posyandu_id = findPosyanduId(desaFromStart, namaPosyandu)

    result.push({
      __excel_row: excelRow,
      nik,
      nama_anak,
      jenis_kelamin: jkCode,
      jenis_kelamin_label: toJKLabel(jkCode),
      tgl_lahir,
      anak_ke: row[COL.anak_ke - 1] ?? '',
      berat_lahir: row[COL.berat_lahir - 1] ?? '',
      panjang_lahir: row[COL.panjang_lahir - 1] ?? '',
      kia: toBool(row[COL.kia - 1]),
      nama_ortu: String(row[COL.nama_ortu - 1] || '').trim(),
      rt: String(row[COL.rt - 1] || '').replace(/\D/g, ''),
      rw: String(row[COL.rw - 1] || '').replace(/\D/g, ''),
      posyandu_id,
      posyandu_nm: namaPosyandu,
    })
  }
  return result
}

/* ===================== BACA FILE ===================== */
async function handleFile(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return
  if (files.some(f => !/\.xlsx?$/i.test(f.name))) {
    await Swal.fire('Format tidak didukung', 'Pilih file .xls atau .xlsx', 'warning')
    return
  }

  parsing.value = true
  rows.value = []
  fileInfo.value = null

  try {
    await loadPosyandu()

    const allRows = []

    for (const f of files) {
      const buf = await f.arrayBuffer()
      // data_only: true agar formula di-skip dan nilai asli dibaca
      const wb = XLSX.read(buf, { type: 'array', cellFormula: false })

      // ── Baca info Desa dari sheet "Start" ──
      let desaFromStart = ''
      if (wb.SheetNames.includes('Start')) {
        const wsStart = wb.Sheets['Start']
        // Desa ada di Start!E5
        const desaCell = wsStart['E5']
        desaFromStart = desaCell ? String(desaCell.v || '').trim() : ''

        const tahunCell = wsStart['H5']
        const bulanCell = wsStart['E6']
        fileInfo.value = {
          desa: desaFromStart,
          tahun: tahunCell ? String(tahunCell.v || '') : '',
          bulan: bulanCell ? String(bulanCell.v || '') : '',
          file: f.name,
        }
      }

      // ── Baca semua sheet P1–P10 (sheet yang namanya /^P\d+$/) ──
      const dataSheets = wb.SheetNames.filter(n => /^P\d+$/i.test(n))

      if (dataSheets.length === 0) {
        // Fallback: kalau tidak ada P1-P10, baca sheet aktif / semua sheet
        const fallbackSheets = wb.SheetNames.filter(n => !['Start','Poskum','PSG'].includes(n))
        dataSheets.push(...fallbackSheets)
      }

      for (const sname of dataSheets) {
        const ws = wb.Sheets[sname]
        // sheet_to_json dengan header:1 → array of arrays, raw:true untuk nilai numerik asli
        const sheetArr = XLSX.utils.sheet_to_json(ws, {
          header: 1,
          defval: null,
          raw: true,
        })
        const parsed = parseSheet(sheetArr, desaFromStart)
        // Tandai asal sheet
        for (const r of parsed) {
          r.__sheet = sname
        }
        allRows.push(...parsed)
      }
    }

    rows.value = allRows
    await nextTick()
    selectOnlyValid()

  } catch (err) {
    console.error(err)
    await Swal.fire('Gagal membaca file', err?.message || 'Kesalahan tidak diketahui', 'error')
  } finally {
    parsing.value = false
  }
}

/* ===================== VALIDASI ===================== */
function validateRow(r) {
  const errs = {}
  if (!/^\d{10,}$/.test(r.nik)) errs.nik = 'NIK minimal 10 digit'
  if (!r.nama_anak) errs.nama_anak = 'Nama wajib'
  if (!r.tgl_lahir) errs.tgl_lahir = 'Tanggal lahir tidak terbaca'
  if (!['L', 'P'].includes(r.jenis_kelamin)) errs.jenis_kelamin = 'JK tidak dikenali'
  return errs
}

/* ===================== PREVIEW ===================== */
const preview = computed(() => {
  // Hitung duplikat NIK di dalam file
  const counts = new Map()
  rows.value.forEach(r => {
    if (/^\d{10,}$/.test(r.nik)) counts.set(r.nik, (counts.get(r.nik) || 0) + 1)
  })

  return rows.value.map((r, i) => {
    const errs = validateRow(r)
    const isDupNik = /^\d{10,}$/.test(r.nik) && (counts.get(r.nik) || 0) > 1
    const messages = [...Object.values(errs)]
    if (isDupNik) messages.push('Duplikat NIK di file')

    return {
      i: i + 1,
      ...r,
      __valid: Object.keys(errs).length === 0 && !isDupNik,
      __errs: errs,
      __dupNik: isDupNik,
      __messages: messages,
    }
  })
})

const validCount = computed(() => preview.value.filter(p => p.__valid).length)

/* ===================== SELEKSI ===================== */
const selectedKeys = ref(new Set())
const rowKey = (p) => `${p.nik}__${p.__sheet}__${p.__excel_row}`

function isSelected(p) { return selectedKeys.value.has(rowKey(p)) }
function setSelected(p, val) {
  const k = rowKey(p)
  const s = new Set(selectedKeys.value)
  if (val) s.add(k); else s.delete(k)
  selectedKeys.value = s
}
function selectAll() {
  selectedKeys.value = new Set(preview.value.map(rowKey))
}
function selectOnlyValid() {
  selectedKeys.value = new Set(preview.value.filter(p => p.__valid).map(rowKey))
}
function clearSelection() { selectedKeys.value = new Set() }

const allSelected = computed(() =>
  preview.value.length > 0 &&
  preview.value.every(p => isSelected(p))
)
function toggleSelectAll(e) {
  if (e?.target?.checked) selectAll(); else clearSelection()
}

const selectedRows = computed(() => preview.value.filter(p => isSelected(p)))
const selectedCount = computed(() => selectedRows.value.length)
const selectedValidCount = computed(() => selectedRows.value.filter(p => p.__valid).length)

/* ===================== IMPOR ===================== */
async function importRows() {
  if (!rows.value.length) {
    await Swal.fire('Tidak ada data', 'Silakan pilih file terlebih dahulu.', 'info'); return
  }
  if (selectedCount.value === 0) {
    await Swal.fire('Belum ada yang dipilih', 'Centang baris yang ingin diimpor.', 'info'); return
  }
  if (selectedValidCount.value === 0) {
    await Swal.fire('Semua yang dipilih tidak valid', 'Periksa kolom Status.', 'warning'); return
  }

  const { isConfirmed } = await Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Impor',
    html: `
      Mode: <b>${importMode.value === 'upsert' ? 'UPSERT (Buat + Update)' : 'HANYA BUAT BARU'}</b><br>
      Dipilih & Valid: <b>${selectedValidCount.value}</b> baris<br><br>
      Lanjutkan?
    `,
    showCancelButton: true,
    confirmButtonText: 'Mulai Impor',
    cancelButtonText: 'Batal',
  })
  if (!isConfirmed) return

  progress.total = selectedValidCount.value
  progress.done = 0
  importing.value = true

  await preloadAnak()

  let created = 0, updated = 0, skipped = 0, failed = 0
  const errors = []

  for (const r of selectedRows.value) {
    if (!r.__valid) continue

    const payload = {
      nik:            r.nik,
      nama_anak:      r.nama_anak,
      jenis_kelamin:  r.jenis_kelamin,
      tgl_lahir:      r.tgl_lahir,
      anak_ke:        r.anak_ke || null,
      berat_lahir:    r.berat_lahir || null,
      panjang_lahir:  r.panjang_lahir || null,
      kia:            !!r.kia,
      nama_ortu:      r.nama_ortu || null,
      rt:             r.rt || null,
      rw:             r.rw || null,
      posyandu_id:    r.posyandu_id || null,
    }

    try {
      const existing = anakByNik.value.get(r.nik) || null

      if (importMode.value === 'create') {
        if (existing) {
          skipped++
        } else {
          const res = await api.post('/anak', payload, { headers: { Accept: 'application/json' } })
          const created_row = res?.data?.data
          if (created_row?.nik) anakByNik.value.set(created_row.nik, created_row)
          created++
        }
      } else {
        // upsert
        if (existing) {
          await api.patch(`/anak/${existing.id}`, payload, { headers: { Accept: 'application/json' } })
          updated++
        } else {
          const res = await api.post('/anak', payload, { headers: { Accept: 'application/json' } })
          const created_row = res?.data?.data
          if (created_row?.nik) anakByNik.value.set(created_row.nik, created_row)
          created++
        }
      }
    } catch (err) {
      failed++
      errors.push({
        nik: r.nik,
        nama: r.nama_anak,
        reason: err?.response?.data?.message || 'Gagal impor',
      })
    } finally {
      progress.done++
    }
  }

  importing.value = false

  const summaryHtml = `
    <div class="text-start">
      <div><b>Mode:</b> ${importMode.value === 'upsert' ? 'UPSERT' : 'HANYA CREATE'}</div>
      <div class="mt-2">✅ Dibuat baru: <b>${created}</b></div>
      ${importMode.value === 'upsert' ? `<div>🔁 Diperbarui: <b>${updated}</b></div>` : ''}
      <div>⏭️ Dilewati: <b>${skipped}</b></div>
      <div>❌ Gagal: <b>${failed}</b></div>
      ${errors.length ? `
        <hr>
        <div class="small"><b>Detail gagal:</b><br>
          ${errors.slice(0, 10).map(e => `${e.nik} – ${e.nama}: ${e.reason}`).join('<br>')}
          ${errors.length > 10 ? '<br>…dan lainnya' : ''}
        </div>` : ''}
    </div>
  `
  await Swal.fire({ icon: failed ? 'warning' : 'success', title: 'Selesai', html: summaryHtml })
}
</script>

<template>
  <div class="container mt-5 mb-5">
    <!-- Back button -->
    <div class="d-flex align-items-center mb-3">
      <RouterLink
        :to="{ name: 'anak.index' }"
        class="btn btn-md btn-secondary rounded shadow border-0 d-inline-flex align-items-center"
      >
        ← Kembali
      </RouterLink>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card border-0 rounded shadow">
          <div class="card-body">
            <h5 class="mb-1">Import Data Anak dari Excel</h5>
            <p class="text-muted small mb-3">
              Mendukung format file aplikasi ASP (kolom tetap: NIK di G, Nama di N, dst.)
              dengan data tersebar di sheet <code>P1–P10</code>.
            </p>

            <!-- File input -->
            <div class="mb-3">
              <label class="form-label fw-bold">File Excel (.xls / .xlsx)</label>
              <input
                type="file"
                class="form-control"
                accept=".xls,.xlsx"
                @change="handleFile"
                multiple
              />
              <div class="form-text">
                Sheet aktif yang dibaca: <b>P1, P2, …, P10</b>. Baris data mulai baris 9.
                <span v-if="loadingPosyandu" class="ms-2 text-warning">
                  ⏳ Memuat daftar Posyandu…
                </span>
              </div>
            </div>

            <!-- Info file -->
            <div v-if="fileInfo" class="alert alert-secondary py-2 mb-3 small">
              <b>File:</b> {{ fileInfo.file }} &nbsp;|&nbsp;
              <b>Desa:</b> {{ fileInfo.desa || '-' }} &nbsp;|&nbsp;
              <b>Tahun:</b> 20{{ fileInfo.tahun }} &nbsp;|&nbsp;
              <b>Bulan:</b> {{ fileInfo.bulan }}
            </div>

            <!-- Mode impor -->
            <div class="mb-3">
              <label class="form-label fw-bold me-3">Mode Impor</label>
              <div class="d-flex gap-4 flex-wrap">
                <label class="form-check">
                  <input class="form-check-input" type="radio" value="upsert" v-model="importMode" />
                  <span class="ms-1">Buat Baru + Update jika sudah ada</span>
                </label>
                <label class="form-check">
                  <input class="form-check-input" type="radio" value="create" v-model="importMode" />
                  <span class="ms-1">Hanya Buat Baru (skip duplikat)</span>
                </label>
              </div>
            </div>

            <!-- Parsing state -->
            <div v-if="parsing" class="alert alert-info">
              ⏳ Membaca file dan mencocokkan Posyandu…
            </div>

            <!-- Summary bar -->
            <div v-else-if="rows.length" class="alert alert-secondary py-2 mb-3">
              <div class="d-flex flex-wrap gap-3 align-items-center">
                <span>Total baris: <b>{{ rows.length }}</b></span>
                <span class="text-success">Valid: <b>{{ validCount }}</b></span>
                <span>Dipilih: <b>{{ selectedCount }}</b></span>
                <span class="text-primary">Dipilih & Valid: <b>{{ selectedValidCount }}</b></span>
              </div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-success btn-sm" @click="selectOnlyValid">
                  ✅ Pilih Hanya Valid
                </button>
                <button class="btn btn-outline-secondary btn-sm" @click="selectAll">
                  Pilih Semua
                </button>
                <button class="btn btn-outline-danger btn-sm" @click="clearSelection">
                  Kosongkan Pilihan
                </button>
              </div>
            </div>

            <!-- Tabel preview -->
            <div class="table-responsive" v-if="rows.length">
              <table class="table table-bordered table-striped table-sm align-middle">
                <thead class="bg-dark text-white">
                  <tr>
                    <th style="width:36px" class="text-center">
                      <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
                    </th>
                    <th>#</th>
                    <th>Sheet</th>
                    <th>Baris</th>
                    <th>NIK</th>
                    <th>Nama Anak</th>
                    <th>JK</th>
                    <th>Tgl Lahir</th>
                    <th>Anak Ke</th>
                    <th>BB Lahir</th>
                    <th>Nama Ortu</th>
                    <th>RT/RW</th>
                    <th>Posyandu</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="p in preview.slice(0, 500)"
                    :key="p.i"
                    :class="{
                      'table-danger':  !p.__valid && !p.__dupNik,
                      'table-warning': p.__dupNik,
                    }"
                  >
                    <td class="text-center">
                      <input
                        type="checkbox"
                        :checked="isSelected(p)"
                        @change="e => setSelected(p, e.target.checked)"
                      />
                    </td>
                    <td>{{ p.i }}</td>
                    <td><span class="badge bg-secondary">{{ p.__sheet }}</span></td>
                    <td>{{ p.__excel_row }}</td>
                    <td class="font-monospace small">{{ p.nik || '-' }}</td>
                    <td>{{ p.nama_anak || '-' }}</td>
                    <td>
                      <span
                        class="badge"
                        :class="p.jenis_kelamin === 'L' ? 'bg-primary' : p.jenis_kelamin === 'P' ? 'bg-danger' : 'bg-secondary'"
                      >
                        {{ p.jenis_kelamin_label }}
                      </span>
                    </td>
                    <td>{{ p.tgl_lahir || '-' }}</td>
                    <td class="text-center">{{ p.anak_ke !== '' ? p.anak_ke : '-' }}</td>
                    <td class="text-center">{{ p.berat_lahir || '-' }}</td>
                    <td class="small">{{ p.nama_ortu || '-' }}</td>
                    <td class="text-center small">{{ p.rt || '-' }}/{{ p.rw || '-' }}</td>
                    <td class="small">
                      <span v-if="p.posyandu_id" class="text-success">
                        ✅ {{ p.posyandu_nm }}
                      </span>
                      <span v-else class="text-muted">
                        {{ p.posyandu_nm || '—' }}
                      </span>
                    </td>
                    <td>
                      <span
                        class="badge"
                        :class="p.__valid ? 'bg-success' : p.__dupNik ? 'bg-warning text-dark' : 'bg-danger'"
                        :title="p.__messages.join('; ')"
                      >
                        {{ p.__valid ? 'OK' : (p.__messages[0] || 'Tidak valid') }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="preview.length > 500">
                    <td colspan="14" class="text-center text-muted small">
                      Menampilkan 500 dari {{ preview.length }} baris.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Progress bar -->
            <div v-if="importing" class="my-3">
              <div class="progress" style="height:22px">
                <div
                  class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                  role="progressbar"
                  :style="{ width: ((progress.done / (progress.total || 1)) * 100) + '%' }"
                >
                  {{ progress.done }} / {{ progress.total }}
                </div>
              </div>
            </div>

            <!-- Tombol impor -->
            <div class="mt-3 d-flex gap-2 align-items-center flex-wrap">
              <button
                class="btn btn-primary"
                :disabled="!rows.length || parsing || importing || selectedValidCount === 0"
                @click="importRows"
              >
                Mulai Impor ({{ selectedValidCount }} dipilih & valid)
              </button>
              <span v-if="selectedValidCount === 0 && rows.length" class="text-muted small">
                Pilih minimal 1 baris valid untuk memulai impor.
              </span>
            </div>

            <!-- Keterangan format -->
            <div class="mt-3 p-3 bg-light rounded small text-muted">
              <b>Format kolom yang dibaca (fixed):</b><br>
              <code>G</code> NIK &nbsp;·&nbsp;
              <code>N</code> Nama Anak &nbsp;·&nbsp;
              <code>O</code> JK (1=L, 2=P) &nbsp;·&nbsp;
              <code>P/Q/R</code> Tgl/Bln/Thn Lahir &nbsp;·&nbsp;
              <code>D</code> Anak Ke &nbsp;·&nbsp;
              <code>E</code> BB Lahir &nbsp;·&nbsp;
              <code>F</code> Panjang Lahir &nbsp;·&nbsp;
              <code>H</code> KIA &nbsp;·&nbsp;
              <code>I</code> Nama Ortu &nbsp;·&nbsp;
              <code>J/K</code> RT/RW &nbsp;·&nbsp;
              <code>L</code> Nama Posyandu
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>