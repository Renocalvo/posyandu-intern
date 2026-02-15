<script setup>
import { ref, reactive, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import Swal from 'sweetalert2'
import * as XLSX from 'xlsx'

const router = useRouter()

/* ===================== STATE ===================== */
const rows = ref([])
const headersFound = ref([])
const parsing = ref(false)
const anakByNik = ref(new Map())

/** 2 MODE SAJA:
 *  'upsert' : create + patch bila duplikat
 *  'create' : hanya create, duplikat di-skip
 */
const importMode = ref('upsert')

const progress = reactive({ total: 0, done: 0 })
const importing = ref(false)

/* ===================== POSYANDU (mapping ketat) ===================== */
const posyanduList = ref([])
const posMap = ref(new Map()) // key: "DESA|NAMA" (normalized) -> id
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
      const desa = p?.desa ?? ''
      const nama = p?.nama ?? ''
      if (desa && nama) m.set(makePosKey(desa, nama), p.id)
    }
    posMap.value = m
  } catch {
    posMap.value = new Map()
  } finally {
    loadingPosyandu.value = false
  }
}

async function preloadAnak() {
  const res = await api.get('/anak', { headers: { Accept: 'application/json' } })
  const arr = Array.isArray(res.data)
    ? res.data
    : Array.isArray(res.data?.data)
    ? res.data.data
    : []

  const m = new Map()
  for (const a of arr) {
    if (a?.nik) m.set(String(a.nik), a)
  }
  anakByNik.value = m
}

/* ====== Ekstrak "Desa SUMBEREJO-Posy. ANGGREK 2" ====== */
function parsePosyText(s) {
  const u = String(s || '').toUpperCase()
  if (!u) return { desa: '', nama: '' }

  let desa = '', nama = ''
  const mDesa = u.match(/DESA\s+([A-Z0-9\s]+)/)
  if (mDesa) desa = mDesa[1].split(/POSY/)[0].replace(/[-–]|POSY.*/g, '').trim()

  const mPosy = u.match(/POSY\.?\s*([A-Z0-9\s]+)/) || u.match(/POSYANDU\s+([A-Z0-9\s]+)/)
  if (mPosy) nama = mPosy[1].trim()

  if (!desa || !nama) {
    const parts = u.split(/[-–]/).map(s => s.trim())
    const pDesa = parts.find(t => t.startsWith('DESA '))
    const pPosy = parts.find(t => t.startsWith('POSY'))
    if (!desa && pDesa) desa = pDesa.replace(/^DESA\s+/, '').trim()
    if (!nama && pPosy) nama = pPosy.replace(/^POSY\.?\s*/, '').replace(/^POSYANDU\s+/, '').trim()
  }
  return { desa, nama }
}

/* ====== Angka Romawi → integer ====== */
function romanToInt(r) {
  const map = {I:1,V:5,X:10,L:50,C:100,D:500,M:1000}
  const s = String(r||'').toUpperCase().replace(/[^IVXLCDM]/g,'')
  if (!s) return null
  let res = 0
  for (let i=0;i<s.length;i++) {
    const v = map[s[i]]||0, n = map[s[i+1]]||0
    res += v < n ? -v : v
  }
  return res>0 && res<4000 ? res : null
}

/* ====== Pecah nama posyandu jadi {base, num} ====== */
function splitBaseNum(name) {
  const raw = norm(name)
  if (!raw) return { base:'', num:null }
  const tokens = raw.split(' ')
  const last = tokens[tokens.length-1]
  let num = null
  if (/^\d+$/.test(last)) {
    num = parseInt(last,10)
    tokens.pop()
  } else {
    const r = romanToInt(last)
    if (r != null) { num = r; tokens.pop() }
  }
  const base = tokens.join(' ').trim()
  return { base, num }
}

/* ====== Cari ID Posyandu (ketat: angka harus match) ====== */
function findPosyanduIdFromText(text) {
  const { desa, nama } = parsePosyText(text)
  if (!desa || !nama) return null

  const exact = posMap.value.get(makePosKey(desa, nama))
  if (exact) return exact

  const qD = norm(desa)
  const qBN = splitBaseNum(nama) // {base, num}

  const candidatesExactNum = []
  const candidatesNoNum = []

  for (const p of posyanduList.value) {
    const pD = norm(p?.desa ?? '')
    if (pD !== qD) continue

    const pBN = splitBaseNum(p?.nama ?? '')
    if (pBN.base !== qBN.base) continue

    if (qBN.num != null) {
      if (pBN.num != null && pBN.num === qBN.num) {
        candidatesExactNum.push(p)
      }
      continue
    }
    if (qBN.num == null && pBN.num == null) {
      candidatesNoNum.push(p)
    }
  }

  if (candidatesExactNum.length === 1) return candidatesExactNum[0].id
  if (candidatesNoNum.length === 1) return candidatesNoNum[0].id
  return null
}

/* ========= Header fuzzy mapping ========= */
function buildHeaderIndex(rowHeaders) {
  const idx = {}
  const headers = rowHeaders.map(h => String(h || '').trim())
  const lower = headers.map(h => h.toLowerCase())
  const normHead = (s) =>
    String(s || '')
      .toLowerCase()
      .replace(/[^\p{L}\p{N}\s]/gu, ' ')
      .replace(/\s+/g, ' ')
      .trim()
  const normd = lower.map(normHead)
  const used = new Set()
  const findIdx = (pred) => {
    for (let i=0;i<headers.length;i++) {
      if (used.has(i)) continue
      if (pred(headers[i], lower[i], normd[i])) { used.add(i); return i }
    }
    return -1
  }

  let i
  i = findIdx((h,l,n) => n === 'nik' || n.includes('nik')); if (i !== -1) idx.nik = i
  i = findIdx((h,l,n) => n.includes('anak ke')); if (i !== -1) idx.anak_ke = i
  i = findIdx((h,l,n) =>
    (n.includes('lahir') && (n.includes('tgl') || n.includes('tanggal') || n.includes('dob') || n.includes('birth'))) ||
    n === 'tgl lahir' || n === 'tanggal lahir' || n === 'tanggal_lahir'
  ); if (i !== -1) idx.tgl_lahir = i
  i = findIdx((h,l,n) => n.includes('kelamin') || n === 'jk' || n === 'gender'); if (i !== -1) idx.jenis_kelamin = i
  i = findIdx((h,l,n) => n.includes('no kk') || (n.includes('kk') && !n.includes('nik'))); if (i !== -1) idx.nomor_KK = i
  i = findIdx((h,l,n) => n.includes('nama') && (n.includes('anak') || n === 'nama')); if (i !== -1) idx.nama_anak = i

  idx.usia_hamil = findIdx((h,l,n) => n.includes('usia hamil')) ?? idx.usia_hamil
  idx.berat_lahir = findIdx((h,l,n) => n.includes('berat') && n.includes('lahir')) ?? idx.berat_lahir
  idx.panjang_lahir = findIdx((h,l,n) => (n.includes('panjang') || n.includes('tinggi')) && n.includes('lahir')) ?? idx.panjang_lahir
  idx.lingkar_kepala_lahir = findIdx((h,l,n) => n.includes('lingkar') && n.includes('kepala') && n.includes('lahir')) ?? idx.lingkar_kepala_lahir
  idx.kia = findIdx((h,l,n) => n === 'kia' || n.includes('buku kia')) ?? idx.kia
  idx.kia_bayi_kecil = findIdx((h,l,n) => n.includes('kia') && n.includes('kecil')) ?? idx.kia_bayi_kecil
  idx.imd = findIdx((h,l,n) => n === 'imd' || n.includes('inisiasi')) ?? idx.imd
  idx.nama_ortu = findIdx((h,l,n) => n.includes('nama') && (n.includes('ortu') || n.includes('orang tua'))) ?? idx.nama_ortu
  idx.nik_ortu = findIdx((h,l,n) => n.includes('nik') && (n.includes('ortu') || n.includes('orang tua'))) ?? idx.nik_ortu
  idx.hp_ortu = findIdx((h,l,n) => (n.includes('hp') || n.includes('telepon')) && (n.includes('ortu') || n.includes('orang tua'))) ?? idx.hp_ortu

  // Posyandu: id / teks alamat
  idx.posyandu_id = findIdx((h,l,n) => n.includes('posyandu id') || n === 'posyandu_id' || n === 'id posyandu') ?? idx.posyandu_id
  idx.posyandu_text = findIdx((h,l,n) => n.includes('alamat') || n.includes('posy') || (n.includes('desa') && n.includes('posy'))) ?? idx.posyandu_text

  // RT/RW
  idx.rt = findIdx((h,l,n) => n === 'rt') ?? idx.rt
  idx.rw = findIdx((h,l,n) => n === 'rw') ?? idx.rw

  headersFound.value = Object.keys(idx)
  return { idx, lowerHeaders: lower }
}

/* ===================== Helpers parsing umum ===================== */
function toBool(v) {
  if (v === true || v === 1) return true
  const s = String(v ?? '').trim().toLowerCase()
  return ['y','ya','yes','true','1'].includes(s)
}
function toGenderCode(v) {
  const s = String(v ?? '').trim().toLowerCase()
  if (['l','laki-laki','laki','male','m','lk','laki2'].includes(s)) return 'L'
  if (['p','perempuan','female','f','pr','wanita'].includes(s)) return 'P'
  return ''
}
function toGenderLabel(code) {
  if (code === 'L') return 'LAKI-LAKI'
  if (code === 'P') return 'PEREMPUAN'
  return '-'
}
function toDateYMD(v) {
  if (!v && v !== 0) return ''
  if (typeof v === 'number') {
    const d = XLSX.SSF.parse_date_code(v)
    if (!d) return ''
    const y = d.y, m = String(d.m).padStart(2,'0'), dd = String(d.d).padStart(2,'0')
    return `${y}-${m}-${dd}`
  }
  const s = String(v).trim()
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s
  let m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/) // DD/MM/YYYY
  if (m) { const dd=m[1].padStart(2,'0'), mm=m[2].padStart(2,'0'), yy=m[3]; return `${yy}-${mm}-${dd}` }
  m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/) // MM/DD/YYYY
  if (m) { const mm=m[1].padStart(2,'0'), dd=m[2].padStart(2,'0'), yy=m[3]; return `${yy}-${mm}-${dd}` }
  return ''
}

/* ===================== Normalisasi baris ===================== */
function normalizeRow(row, headerIdx) {
  const get = (key) => {
    const i = headerIdx.idx[key]
    return i != null ? row[i] : undefined
  }

  const nikDigits = String(get('nik') ?? '').replace(/\D/g, '')
  const kkDigits  = String(get('nomor_KK') ?? '').replace(/\D/g, '')
  const posIdCell = get('posyandu_id')
  const posText   = get('posyandu_text')

  let posyandu_id = null
  if (posIdCell !== undefined && posIdCell !== '') {
    const asDigits = String(posIdCell).replace(/\D/g, '')
    posyandu_id = asDigits ? Number(asDigits) : null
  } else if (posText) {
    posyandu_id = findPosyanduIdFromText(posText)
  }

  const jkCode = toGenderCode(get('jenis_kelamin'))

  return {
    nik: nikDigits,
    anak_ke: get('anak_ke') ?? '',
    tgl_lahir: toDateYMD(get('tgl_lahir')),
    jenis_kelamin: jkCode,
    jenis_kelamin_label: toGenderLabel(jkCode),
    nomor_KK: kkDigits,
    nama_anak: (get('nama_anak') ?? '').toString().trim(),
    usia_hamil: get('usia_hamil') ?? '',
    berat_lahir: get('berat_lahir') ?? '',
    panjang_lahir: get('panjang_lahir') ?? '',
    lingkar_kepala_lahir: get('lingkar_kepala_lahir') ?? '',
    kia: toBool(get('kia')),
    kia_bayi_kecil: toBool(get('kia_bayi_kecil')),
    imd: toBool(get('imd')),
    nama_ortu: (get('nama_ortu') ?? '').toString().trim(),
    nik_ortu: String(get('nik_ortu') ?? '').replace(/\D/g, ''),
    hp_ortu: String(get('hp_ortu') ?? '').replace(/[^\d+]/g, ''),
    posyandu_id,
    posyandu_text: posText || '',
    rt: String(get('rt') ?? '').replace(/\D/g, ''),
    rw: String(get('rw') ?? '').replace(/\D/g, ''),
  }
}

/* ===================== Validasi ringkas ===================== */
function validateRow(r) {
  const errs = {}
  if (!/^\d{10,}$/.test(r.nik)) errs.nik = 'NIK minimal 10 digit'
  if (!r.nama_anak) errs.nama_anak = 'Nama wajib'
  if (!r.tgl_lahir) errs.tgl_lahir = 'Tanggal lahir wajib'
  if (!['L','P'].includes(r.jenis_kelamin)) errs.jenis_kelamin = 'Pilih L/P'
  return errs
}

/* ===================== BACA FILE ===================== */
async function handleFile(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return
  if (files.some(f => !/\.xlsx?$/.test(f.name.toLowerCase()))) {
    await Swal.fire('Format tidak didukung', 'Pilih file .xls atau .xlsx', 'warning'); return
  }

  parsing.value = true
  rows.value = []
  headersFound.value = []
  try {
    await loadPosyandu() // penting untuk mapping posyandu

    const allRows = []
    for (const f of files) {
      const buf = await f.arrayBuffer()
      const wb = XLSX.read(buf, { type: 'array' })
      const sheet = wb.Sheets[wb.SheetNames[0]]
      const arr = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false })
      if (!arr.length) continue

      const headerRow = arr[0]
      const { idx } = buildHeaderIndex(headerRow)
      headersFound.value = Array.from(new Set([...headersFound.value, ...Object.keys(idx)]))

      const dataRows = arr.slice(1).filter(r => r.some(cell => (cell ?? '') !== ''))
      const mapped = dataRows.map((r, i) => {
        const n = normalizeRow(r, { idx })
        return { __row: i + 2, ...n } // simpan nomor baris Excel (mulai dari 2 karena header = 1)
      })
      allRows.push(...mapped)
    }

    // simpan semua baris (tanpa dedupe) agar bisa dipilih/ditandai
    rows.value = allRows

    // auto-select: pilih default baris yang valid
    await nextTick()
    selectOnlyValid()
  } catch (err) {
    console.error(err)
    await Swal.fire('Gagal membaca file', (err?.message || 'Kesalahan tidak diketahui'), 'error')
  } finally {
    parsing.value = false
  }
}

function isDuplicateError(e) {
  const code = e?.response?.status
  const msg  = e?.response?.data?.message || ''
  return code === 409 || /sudah ada|exists|duplicate|unik/i.test(msg)
}

/* ===================== PREVIEW (tandai invalid & duplikat) ===================== */
const preview = computed(() => {
  // Hitung duplikasi NIK (yang panjangnya ≥ 10 digit)
  const counts = new Map()
  rows.value.forEach(r => {
    const key = /^\d{10,}$/.test(r.nik) ? r.nik : null
    if (!key) return
    counts.set(key, (counts.get(key) || 0) + 1)
  })

  return rows.value.map((r, i) => {
    const errs = validateRow(r)
    const isDupNik = /^\d{10,}$/.test(r.nik) && (counts.get(r.nik) || 0) > 1
    const messages = []
    if (Object.keys(errs).length) messages.push(...Object.values(errs))
    if (isDupNik) messages.push('Duplikat NIK di file')

    return {
      i: i + 1,
      __excel_row: r.__row,
      ...r,
      __valid: Object.keys(errs).length === 0 && !isDupNik, // duplikat dianggap tidak valid untuk impor
      __errs: errs,
      __dupNik: isDupNik,
      __messages: messages
    }
  })
})

const validCount = computed(() => preview.value.filter(p => p.__valid).length)

/* ===================== SELEKSI (checkbox) ===================== */
const selectedKeys = ref(new Set())

const rowKey = (p) => `${p.nik || 'NONIK'}__${p.__excel_row || p.i}`

function isSelected(p) { return selectedKeys.value.has(rowKey(p)) }
function setSelected(p, val) {
  const k = rowKey(p)
  if (val) selectedKeys.value.add(k)
  else selectedKeys.value.delete(k)
}
function selectAll() {
  const s = new Set(selectedKeys.value)
  for (const p of preview.value) s.add(rowKey(p))
  selectedKeys.value = s
}
function selectOnlyValid() {
  const s = new Set()
  for (const p of preview.value) if (p.__valid) s.add(rowKey(p))
  selectedKeys.value = s
}
function clearSelection() { selectedKeys.value = new Set() }

const allSelected = computed(() => {
  if (!preview.value.length) return false
  let cnt = 0
  for (const p of preview.value) if (isSelected(p)) cnt++
  return cnt === preview.value.length
})
function toggleSelectAll(e) {
  if (e?.target?.checked) selectAll()
  else clearSelection()
}

const selectedRows = computed(() => preview.value.filter(p => isSelected(p)))
const selectedCount = computed(() => selectedRows.value.length)
const selectedValidCount = computed(() => selectedRows.value.filter(p => p.__valid).length)

/* ===================== IMPOR: hanya baris DIPILIH & VALID ===================== */
async function importRows() {
  if (!rows.value.length) {
    await Swal.fire('Tidak ada data', 'Silakan pilih file terlebih dahulu.', 'info')
    return
  }

  if (selectedCount.value === 0) {
    await Swal.fire('Belum ada yang dipilih', 'Centang baris yang ingin diimpor, atau klik "Pilih Hanya Valid".', 'info')
    return
  }

  if (selectedValidCount.value === 0) {
    const detail = selectedRows.value.slice(0, 10).map(p =>
      `Baris Excel ${p.__excel_row ?? '-'} (Idx ${p.i}): ${p.__messages.join(', ') || 'Tidak valid'}`
    ).join('<br>')
    await Swal.fire({
      icon: 'warning',
      title: 'Semua yang dipilih tidak valid',
      html: `<div class="text-start small">${detail}${selectedRows.value.length>10?'...':''}</div>`
    })
    return
  }

  const { isConfirmed } = await Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Impor',
    html: `
      Mode: <b>${importMode.value === 'upsert' ? 'UPSERT (Create + Patch)' : 'HANYA CREATE (Skip Duplikat)'}</b><br>
      Dipilih: <b>${selectedCount.value}</b> baris<br>
      <span class="text-success">Dipilih & Valid:</span> <b>${selectedValidCount.value}</b> baris<br><br>
      Lanjutkan impor?
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
      nik: r.nik,
      anak_ke: r.anak_ke || null,
      tgl_lahir: r.tgl_lahir,
      jenis_kelamin: r.jenis_kelamin,    // 'L'/'P'
      nomor_KK: r.nomor_KK || null,
      nama_anak: r.nama_anak,
      usia_hamil: r.usia_hamil || null,
      berat_lahir: r.berat_lahir || null,
      panjang_lahir: r.panjang_lahir || null,
      lingkar_kepala_lahir: r.lingkar_kepala_lahir || null,
      kia: !!r.kia,
      kia_bayi_kecil: !!r.kia_bayi_kecil,
      imd: !!r.imd,
      nama_ortu: r.nama_ortu || null,
      nik_ortu: r.nik_ortu || null,
      hp_ortu: r.hp_ortu || null,
      posyandu_id: r.posyandu_id || null,
      rt: r.rt || null,
      rw: r.rw || null,
    }

      try {
        const existing = anakByNik.value.get(r.nik) || null

        if (importMode.value === 'create') {
          if (existing) {
            skipped++
          } else {
            const res = await api.post('/anak', payload, {
              headers: { Accept: 'application/json' }
            })
            const createdRow = res?.data?.data
            if (createdRow?.nik) {
              anakByNik.value.set(createdRow.nik, createdRow)
            }
            created++
          }
        } else {
          if (existing) {
            await api.patch(`/anak/${existing.id}`, payload, {
              headers: { Accept: 'application/json' }
            })
            updated++
          } else {
            const res = await api.post('/anak', payload, {
              headers: { Accept: 'application/json' }
            })
            const createdRow = res?.data?.data
            if (createdRow?.nik) {
              anakByNik.value.set(createdRow.nik, createdRow)
            }
            created++
          }
        }
      } catch (err) {
        failed++
        errors.push({
          nik: r.nik,
          reason: err?.response?.data?.message || 'Gagal impor'
        })
      } finally {
        progress.done++
      }
    }
  importing.value = false

  const summaryHtml = `
    <div class="text-start">
      <div><b>Mode:</b> ${importMode.value === 'upsert' ? 'UPSERT (Create + Patch)' : 'HANYA CREATE (Skip Duplikat)'}</div>
      <div><b>Dipilih:</b> ${selectedCount.value} · <span class="text-success">Dipilih & Valid:</span> ${progress.total}</div>
      <div class="mt-2">✅ Created: <b>${created}</b></div>
      ${importMode.value === 'upsert' ? `<div>🔁 Updated: <b>${updated}</b></div>` : ''}
      <div>⏭️ Skipped: <b>${skipped}</b></div>
      <div>❌ Failed: <b>${failed}</b></div>
      ${errors.length ? `<hr><div class="small"><b>Gagal:</b><br>${errors.slice(0,8).map(e=>`${e.nik}: ${e.reason}`).join('<br>')}${errors.length>8?'<br>…':''}</div>` : ''}
    </div>
  `
  await Swal.fire({ icon: failed ? 'warning' : 'success', title: 'Selesai', html: summaryHtml })

}
</script>

<template>
  <div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <RouterLink
          :to="{ name: 'anak.index' }"
          class="btn btn-md btn-secondary rounded shadow border-0 d-inline-flex align-items-center"
        >
          ← Kembali
        </RouterLink>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card border-0 rounded shadow">
          <div class="card-body">
            <h5 class="mb-3">Import Data Anak dari Excel</h5>

            <div class="mb-3">
              <label class="form-label fw-bold">File Excel (.xls / .xlsx)</label>
              <input type="file" class="form-control" accept=".xls,.xlsx" @change="handleFile" multiple />
              <div class="form-text">
                Gunakan sheet pertama. Baris pertama dianggap header.
                <span v-if="loadingPosyandu" class="ms-2 text-muted">Memuat daftar Posyandu…</span>
              </div>
            </div>

            <!-- 2 Mode Impor -->
            <div class="mb-3">
              <label class="form-label fw-bold me-3">Mode Impor</label>
              <div class="d-flex gap-4 flex-wrap">
                <label class="form-check">
                  <input class="form-check-input" type="radio" value="upsert" v-model="importMode" />
                  <span class="ms-1">Buat Data Baru dan Update Data</span>
                </label>
                <label class="form-check">
                  <input class="form-check-input" type="radio" value="create" v-model="importMode" />
                  <span class="ms-1">Hanya Buat Data Baru</span>
                </label>
              </div>
            </div>

            <div v-if="parsing" class="alert alert-info">Membaca file…</div>
            <div v-else-if="rows.length" class="alert alert-secondary">
              <div><b>Header dikenali:</b> {{ headersFound.join(', ') || '-' }}</div>
              <div>
                Baris terbaca: <b>{{ rows.length }}</b>.
                Valid: <b>{{ validCount }}</b>.
                Dipilih: <b>{{ selectedCount }}</b>.
                <span class="text-success">Dipilih & Valid: <b>{{ selectedValidCount }}</b></span>.
              </div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary btn-sm" @click="selectOnlyValid">Pilih Hanya Valid</button>
                <button class="btn btn-outline-secondary btn-sm" @click="selectAll">Pilih Semua</button>
                <button class="btn btn-outline-danger btn-sm" @click="clearSelection">Kosongkan Pilihan</button>
              </div>
            </div>

            <div class="table-responsive" v-if="rows.length">
              <table class="table table-bordered table-striped align-middle">
                <thead class="bg-dark text-white">
                  <tr>
                    <th style="width:36px;" class="text-center">
                      <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
                    </th>
                    <th>#</th>
                    <th>Baris Excel</th>
                    <th>NIK</th>
                    <th>Nama Anak</th>
                    <th>JK</th>
                    <th>Tgl Lahir</th>
                    <th>No KK</th>
                    <th>Anak Ke</th>
                    <th>HP Ortu</th>
                    <th>Posyandu</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in preview.slice(0,300)" :key="p.i">
                    <td class="text-center">
                      <input type="checkbox" :checked="isSelected(p)" @change="e => setSelected(p, e.target.checked)" />
                    </td>
                    <td>{{ p.i }}</td>
                    <td>{{ p.__excel_row ?? '-' }}</td>
                    <td>{{ p.nik || '-' }}</td>
                    <td>{{ p.nama_anak || '-' }}</td>
                    <td>{{ p.jenis_kelamin_label }}</td>
                    <td>{{ p.tgl_lahir || '-' }}</td>
                    <td>{{ p.nomor_KK || '-' }}</td>
                    <td>{{ p.anak_ke || '-' }}</td>
                    <td>{{ p.hp_ortu || '-' }}</td>
                    <td>
                      <span v-if="p.posyandu_id">ID: {{ p.posyandu_id }}</span>
                      <span v-else class="text-muted">—</span>
                      <div v-if="p.posyandu_text" class="small text-muted">{{ p.posyandu_text }}</div>
                    </td>
                    <td>
                      <span
                        :class="[
                          'badge',
                          p.__valid
                            ? 'bg-success'
                            : (p.__dupNik ? 'bg-warning text-dark' : 'bg-danger')
                        ]"
                      >
                        {{ p.__valid ? 'OK' : (p.__messages.join('; ') || 'Tidak valid') }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="preview.length > 300">
                    <td colspan="12" class="text-center text-muted">…menampilkan 300 baris pertama dari {{ preview.length }}.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="importing" class="my-3">
              <div class="progress" style="height: 20px;">
                <div
                  class="progress-bar progress-bar-striped progress-bar-animated"
                  role="progressbar"
                  :style="{ width: ((progress.done / progress.total)*100 || 0) + '%' }"
                >
                  {{ progress.done }} / {{ progress.total }}
                </div>
              </div>
            </div>

            <div class="mt-3 d-flex gap-2">
              <button
                class="btn btn-primary"
                :disabled="!rows.length || parsing || importing || selectedValidCount === 0"
                @click="importRows"
                title="Impor hanya baris yang dipilih & valid"
              >
                Mulai Impor ({{ selectedValidCount }} dipilih & valid)
              </button>
            </div>

            <div class="mt-3 small text-muted">
              Kolom didukung: NIK (≥10 digit), Anak Ke, Tgl Lahir (YYYY-MM-DD/serial Excel/DD-MM-YYYY),
              Jenis Kelamin (L/P → LAKI-LAKI/PEREMPUAN), Nomor KK (digit), Nama Anak, Usia Hamil,
              Berat/Panjang/Lingkar Kepala Lahir, KIA, KIA Bayi Kecil, IMD, Nama/NIK/HP Ortu,
              Posyandu (ID atau teks "Desa … - Posy. …"), RT, RW.
              Baris tidak valid & duplikat tetap ditampilkan dengan status.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
