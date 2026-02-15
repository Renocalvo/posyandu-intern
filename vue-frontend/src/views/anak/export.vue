<script setup>
import { ref, computed, onMounted } from 'vue'
import { posyanduLabelFromRow as posyanduLabel } from '../../utils/labels'
import api from '../../api'
import Swal from 'sweetalert2'
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

/* =================== STATE =================== */
const anak = ref([])
const loading = ref(false)
const errorMsg = ref('')

/* =================== FILTERS =================== */
const q = ref('')                 // search bebas: NIK / nama anak / nama ortu
const filterPosyandu = ref('')    // label posyandu (exact) -> pakai util
const filterDesa = ref('')        // DESA
const filterJK = ref('')          // '', 'L', 'P'
const filterKIA = ref('')         // '', 'yes', 'no'
const filterKiaBayiKecil = ref('')// '', 'yes', 'no'
const filterIMD = ref('')         // '', 'yes', 'no'
const filterRT = ref('')          // string/angka
const filterRW = ref('')          // string/angka
const nikMode = ref('ALL')        // 'ALL' | 'LT16' | 'EQ16'

// Rentang tanggal lahir
const filterTglFrom = ref('')     // yyyy-mm-dd
const filterTglTo = ref('')       // yyyy-mm-dd

// Rentang numerik
const beratFrom = ref('')         // kg
const beratTo = ref('')
const panjangFrom = ref('')       // cm
const panjangTo = ref('')
const lingkarFrom = ref('')       // cm
const lingkarTo = ref('')

/* =================== LOAD DATA =================== */
const fetchDataAnak = async () => {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await api.get('/anak', { headers: { Accept: 'application/json' } })
    anak.value = Array.isArray(res.data) ? res.data
      : Array.isArray(res.data?.data) ? res.data.data
      : Array.isArray(res.data?.data?.data) ? res.data.data.data
      : []
  } catch (e) {
    anak.value = []
    errorMsg.value = e?.response?.data?.message ?? 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}
onMounted(fetchDataAnak)

/* =================== EXTRACTORS =================== */
const get = (o, ...paths) => {
  for (const p of paths) {
    const v = p.split('.').reduce((acc, k) => (acc && acc[k] !== undefined ? acc[k] : undefined), o)
    if (v !== undefined && v !== null && v !== '') return v
  }
  return ''
}
function getDesa(row) {
  return get(row, 'posyandu.desa', 'desa_posyandu', 'anak.desa_posyandu', 'anak.desa') || ''
}
function getPosyanduName(row) {
  return get(row, 'posyandu.nama', 'nama_posyandu', 'anak.nama_posyandu', 'posyandu_nama') || ''
}

/* =================== OPTIONS =================== */
const rows = computed(() => {
  const u = anak.value
  if (Array.isArray(u)) return u
  if (Array.isArray(u?.data)) return u.data
  if (Array.isArray(u?.data?.data)) return u.data.data
  return []
})
const posyanduOptions = computed(() => {
  const labels = rows.value.map(r => posyanduLabel(r)).filter(Boolean)
  return Array.from(new Set(labels)).sort((a, b) => a.localeCompare(b, 'id'))
})
const desaOptions = computed(() => {
  const list = rows.value.map(getDesa).filter(Boolean)
  return Array.from(new Set(list)).sort((a, b) => a.localeCompare(b, 'id'))
})
const jkOptions = [
  { label: '— Semua —', value: '' },
  { label: 'Laki-Laki', value: 'L' },
  { label: 'Perempuan', value: 'P' }
]
const boolOptions = [
  { label: '— Semua —', value: '' },
  { label: 'Ya', value: 'yes' },
  { label: 'Tidak', value: 'no' }
]
const nikOptions = [
  { label: 'Semua', value: 'ALL' },
  { label: 'Hanya NIK < 16 digit', value: 'LT16' },
  { label: 'Hanya NIK = 16 digit', value: 'EQ16' },
]

/* =================== HELPERS =================== */
const toYesNo = (v) =>
  v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true' ? 'Ya' : 'Tidak'
const fmtDate = (v) => (v ? new Date(v).toLocaleDateString('id-ID') : '-')
const safe = (v) => (v ?? v === 0 ? v : '-')
const genderLabel = (jk) => !jk ? '-' : (jk.toUpperCase() === 'P' ? 'Perempuan' : jk.toUpperCase() === 'L' ? 'Laki-Laki' : jk)

const normalizeBool = (v) =>
  (v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true')
const matchTriBool = (value, choice) => {
  if (!choice) return true
  const b = normalizeBool(value)
  return choice === 'yes' ? b === true : b === false
}
const parseNum = (v) => {
  const n = parseFloat(v)
  return Number.isFinite(n) ? n : null
}
const inNumRange = (val, from, to) => {
  const n = parseNum(val)
  if (n === null) return false
  const f = parseNum(from)
  const t = parseNum(to)
  if (f !== null && n < f) return false
  if (t !== null && n > t) return false
  return true
}
const inDateRange = (value, from, to) => {
  if (!value) return false
  const d = new Date(value)
  if (from) {
    const f = new Date(from)
    if (d < f) return false
  }
  if (to) {
    const t = new Date(to)
    t.setHours(23, 59, 59, 999)
    if (d > t) return false
  }
  return true
}

/* =================== FILTERED =================== */
const filteredRows = computed(() => {
  const keyword = q.value.trim().toLowerCase()
  const posy = filterPosyandu.value
  const desa = filterDesa.value
  const jk = (filterJK.value || '').toUpperCase()
  const rt = filterRT.value.toString().trim()
  const rw = filterRW.value.toString().trim()
  const from = filterTglFrom.value
  const to = filterTglTo.value
  const nikSel = nikMode.value

  return rows.value.filter(item => {
    // NIK length
    const nik = String(item?.nik ?? '').trim()
    if (nikSel === 'LT16' && nik.length >= 16) return false
    if (nikSel === 'EQ16' && nik.length !== 16) return false

    // Posyandu & Desa
    if (posy && posyanduLabel(item) !== posy) return false
    if (desa && getDesa(item) !== desa) return false

    // JK
    if (jk) {
      const itemJK = String(item?.jenis_kelamin ?? '').toUpperCase()
      if (itemJK !== jk) return false
    }

    // KIA / KIA BK / IMD
    if (!matchTriBool(item?.kia, filterKIA.value)) return false
    if (!matchTriBool(item?.kia_bayi_kecil, filterKiaBayiKecil.value)) return false
    if (!matchTriBool(item?.imd, filterIMD.value)) return false

    // RT / RW
    if (rt && String(item?.rt ?? '').trim() !== rt) return false
    if (rw && String(item?.rw ?? '').trim() !== rw) return false

    // Tgl lahir
    if ((from || to) && !inDateRange(item?.tgl_lahir, from, to)) return false

    // Numerik lahir
    if ((beratFrom.value || beratTo.value) &&
        !inNumRange(item?.berat_lahir, beratFrom.value, beratTo.value)) return false
    if ((panjangFrom.value || panjangTo.value) &&
        !inNumRange(item?.panjang_lahir, panjangFrom.value, panjangTo.value)) return false
    if ((lingkarFrom.value || lingkarTo.value) &&
        !inNumRange(item?.lingkar_kepala_lahir, lingkarFrom.value, lingkarTo.value)) return false

    // Keyword: NIK / Nama anak / Nama ortu
    if (!keyword) return true
    const nikStr   = String(item?.nik ?? '').toLowerCase()
    const namaStr  = String(item?.nama_anak ?? '').toLowerCase()
    const ortuStr  = String(item?.nama_ortu ?? '').toLowerCase()
    return nikStr.includes(keyword) || namaStr.includes(keyword) || ortuStr.includes(keyword)
  })
})

/* =================== RESET =================== */
const resetFilter = () => {
  q.value = ''
  filterPosyandu.value = ''
  filterDesa.value = ''
  filterJK.value = ''
  filterKIA.value = ''
  filterKiaBayiKecil.value = ''
  filterIMD.value = ''
  filterRT.value = ''
  filterRW.value = ''
  filterTglFrom.value = ''
  filterTglTo.value = ''
  beratFrom.value = ''
  beratTo.value = ''
  panjangFrom.value = ''
  panjangTo.value = ''
  lingkarFrom.value = ''
  lingkarTo.value = ''
  nikMode.value = 'ALL'
}

/* =================== NAMA FILE DINAMIS =================== */
// Parse "Desa X - Posy. Y" dari label util
// ========= NAMA FILE DINAMIS =========

// Override singkatan DESA (sesuai permintaan)
const overrides = {
  'ORO-ORO OMBO': 'ORO',
  'NGAGLIK': 'NGK',
  'SUMBEREJO': 'SBJ',
  'PESANGGRAHAN': 'PSG',
  'SONGGOKERTO': 'SGK',
}

// Parse "Desa X - Posy. Y" dari label util
function parsePosyanduFromLabel(label, sampleRows = []) {
  if (!label) return { desa: '', pos: '' }
  const m = label.match(/Desa\s*(.+?)\s*-\s*Posy\.\s*(.+)$/i)
  if (m) return { desa: (m[1] || '').trim(), pos: (m[2] || '').trim() }
  const it = sampleRows.find(r => (posyanduLabel(r) || '') === label)
  return { desa: it ? getDesa(it) : '', pos: it ? getPosyanduName(it) : '' }
}

// Singkatan Desa: cek overrides → fallback inisial
function abbrDesa(desa) {
  const up = (desa || '').toString().trim().toUpperCase()
  if (!up) return ''
  if (overrides[up]) return overrides[up]
  const words = up.split(/\s+/).filter(Boolean)
  const stop = /^(DESA|DS|DS\.|KEL|KEL\.|KELURAHAN)$/i
  const filtered = words.filter(w => !stop.test(w))
  if (!filtered.length) filtered.push(...words)
  return (filtered.length === 1 ? filtered[0].slice(0, 2) : filtered.map(w => w[0]).join('')).toUpperCase()
}

// Singkatan Posyandu: huruf awal + semua digit (jika ada)
function abbrPos(posName) {
  if (!posName) return ''
  const letter = (posName.replace(/[^A-Za-z]/g, '')[0] || '').toUpperCase()
  const digits = (posName.match(/\d+/g)?.join('') || '')
  return `${letter}${digits}`
}

/**
 * Bangun nama file:
 * - Jika ada filter Posyandu: "ID <DESA> SBJ <POS> A2.xlsx"
 * - ELSE jika ada filter Desa: "ID <DESA> ALL.xlsx"
 * - ELSE: fallback timestamp
 */
 function buildExportFileName({
   labelPos,
   desaName,
   rowsSample,
 }) {
   const hasPos = !!(labelPos && String(labelPos).trim())
   const hasDesa = !!(desaName && String(desaName).trim())

   if (hasPos) {
     const { desa, pos } = parsePosyanduFromLabel(labelPos, rowsSample)
     const d = abbrDesa(desa)
     const p = abbrPos(pos)
     return `ID ${d} ${p}.xlsx`
   }

   if (hasDesa) {
     const d = abbrDesa(desaName)
     return `ID ${d} ALL.xlsx`
   }

   const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')
   return `ID ALL DATA ${stamp}.xlsx`
 }


/* =================== EXPORT =================== */
function autoFitColumns(worksheet) {
  worksheet.columns.forEach((col) => {
    let max = 10
    col.eachCell({ includeEmpty: true }, (c) => {
      const v = c.value == null ? '' : c.value.richText ? c.value.richText.map(t => t.text).join('') : String(c.value)
      max = Math.max(max, v.length + 2)
    })
    col.width = Math.min(Math.max(max, 10), 60)
  })
}

async function exportFilteredToExcel() {
  if (!filteredRows.value.length) {
    await Swal.fire('Tidak ada', 'Tidak ada data sesuai filter untuk diekspor.', 'info')
    return
  }

  // Nama file dinamis bila filter Posyandu terisi
  const hasPos = !!(filterPosyandu.value && filterPosyandu.value.trim())
  const hasDesa = !!(filterDesa.value && filterDesa.value.trim())

  const fname = buildExportFileName({
    labelPos: hasPos ? filterPosyandu.value : '',
    desaName: hasDesa ? filterDesa.value : '',
    rowsSample: rows.value,
    suffixDesa: 'ALL'
  })

  // Konfirmasi
  const { isConfirmed } = await Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Ekspor',
    html: `
      <div class="text-start">
        <div>Jumlah baris: <b>${filteredRows.value.length}</b></div>
        ${hasPos ? `<div>Filter Posyandu: <span class="badge bg-dark">${filterPosyandu.value}</span></div>` : ''}
        ${hasDesa ? `<div>Filter Desa: <span class="badge bg-secondary">${filterDesa.value}</span></div>` : ''}
        ${(hasPos && hasDesa) ? `<div class="mt-1 small text-muted">Nama file mengikuti <b>Posyandu</b> (prioritas Posyandu).</div>` : ''}
        <div class="mt-2">Nama file:</div>
        <code>${fname}</code>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Ekspor',
    cancelButtonText: 'Batal',
  })
  if (!isConfirmed) return

  // Header persis contoh
  const header = [
    'No','anak_ke','tgl_lahir','jenis_kelamin','nomor_KK','NIK','nama_anak',
    'usia_hamil','berat_lahir','panjang_lahir','lingkar_kepala_lahir',
    'kia','kia_bayi_kecil','imd','nama_ortu','nik_ortu','hp_ortu','alamat','rt','rw'
  ]

  const toYa = (v) => (v === true || String(v).toLowerCase() === 'true' || v === 1 || v === '1') ? 'Ya' : ''
  const formatAlamat = (desaVal, posVal) => {
    const d = (desaVal || '').toString().trim()
    const p = (posVal || '').toString().trim()
    if (d && p) return `Desa ${d} - Posy. ${p}`
    if (d) return `Desa ${d}`
    if (p) return `Posy. ${p}`
    return ''
  }

  const rowsData = filteredRows.value.map((item, i) => {
    const label = posyanduLabel(item) || ''
    const { desa, pos } = parsePosyanduFromLabel(label, [item])
    const alamat = formatAlamat(desa || getDesa(item), pos || getPosyanduName(item))

    const nik = String(item?.nik ?? '').trim()
    const nikOut = nik || '-/(BELUM ADA)'

    return [
      i + 1,
      item?.anak_ke ?? '',
      item?.tgl_lahir ?? '',
      item?.jenis_kelamin ?? '',
      item?.nomor_KK ?? '',
      nikOut,
      item?.nama_anak ?? '',
      item?.usia_hamil ?? '',
      item?.berat_lahir ?? '',
      item?.panjang_lahir ?? '',
      item?.lingkar_kepala_lahir ?? '',
      toYa(item?.kia),
      toYa(item?.kia_bayi_kecil),
      toYa(item?.imd),
      item?.nama_ortu ?? '',
      item?.nik_ortu ?? '',
      item?.hp_ortu ?? '',
      alamat,
      item?.rt ?? '',
      item?.rw ?? ''
    ]
  })

  try {
    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('EXPORT_ANAK')

    // Header: kuning, border, align kiri
    ws.addRow(header)
    ws.getRow(1).eachCell((cell) => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFF00' } }
      cell.alignment = { vertical: 'middle', horizontal: 'left' }
      cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
    })

    // Body + paksa TEXT untuk nomor_KK (col 5) & NIK (col 6)
    rowsData.forEach(r => ws.addRow(r))
    ws.eachRow({ includeEmpty: false }, (row, rowNumber) => {
      if (rowNumber === 1) return
      row.eachCell((cell, col) => {
        if (col === 5 || col === 6) {
          const v = cell.value ?? ''
          cell.value = v === '' ? '' : { richText: [{ text: String(v) }] }
          cell.numFmt = '@'
        }
        cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
        cell.alignment = { vertical: 'middle', horizontal: 'left' }
      })
    })

    autoFitColumns(ws)

    const buf = await wb.xlsx.writeBuffer()
    saveAs(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), fname)

    await Swal.fire('Berhasil', `Ekspor <b>${rowsData.length}</b> baris ke <b>${fname}</b>.`, 'success')
  } catch (e) {
    await Swal.fire('Gagal ekspor', e?.message ?? 'Terjadi kesalahan saat membuat file Excel.', 'error')
  }
}
</script>

<template>
  <div class="container mt-5 mb-5">
    <!-- Header sederhana (tanpa tombol Tambah/Import) -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <RouterLink
          :to="{ name: 'anak.index' }"
          class="btn btn-md btn-secondary rounded shadow border-0 d-inline-flex align-items-center"
        >
          ← Kembali
        </RouterLink>
        <h5 class="mb-0">Export Data Anak</h5>
      </div>
    </div>

    <!-- 🔎 Toolbar Search & Filters -->
    <div class="card border-0 rounded shadow mb-3">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <!-- Search -->
          <div class="col-lg-4">
            <label class="form-label mb-1">Cari (NIK / Nama Anak / Nama Ortu)</label>
            <input v-model="q" type="text" class="form-control" placeholder="Ketik minimal 2 karakter…" />
          </div>

          <!-- Tanggal Lahir -->
          <div class="col-lg-4">
            <label class="form-label mb-1 d-block">Tanggal Lahir (Dari–Sampai)</label>
            <div class="input-group">
              <input v-model="filterTglFrom" type="date" class="form-control" />
              <span class="input-group-text">s.d.</span>
              <input v-model="filterTglTo" type="date" class="form-control" />
            </div>
          </div>

          <!-- Posyandu (label util) -->
          <div class="col-lg-4">
            <label class="form-label mb-1">Filter Posyandu</label>
            <select v-model="filterPosyandu" class="form-select">
              <option value="" class="placeholder-option">— Semua Posyandu —</option>
              <option v-for="opt in posyanduOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>

          <!-- Desa -->
          <div class="col-lg-3">
            <label class="form-label mb-1">Filter Desa</label>
            <select v-model="filterDesa" class="form-select">
              <option value="">— Semua Desa —</option>
              <option v-for="d in desaOptions" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>

          <!-- JK -->
          <div class="col-lg-3">
            <label class="form-label mb-1">Jenis Kelamin</label>
            <select v-model="filterJK" class="form-select">
              <option v-for="o in jkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <!-- KIA / KIA BK / IMD -->
          <div class="col-lg-2">
            <label class="form-label mb-1">KIA</label>
            <select v-model="filterKIA" class="form-select">
              <option v-for="o in boolOptions" :key="'kia'+o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="col-lg-2">
            <label class="form-label mb-1">KIA BK</label>
            <select v-model="filterKiaBayiKecil" class="form-select">
              <option v-for="o in boolOptions" :key="'kiabk'+o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="col-lg-2">
            <label class="form-label mb-1">IMD</label>
            <select v-model="filterIMD" class="form-select">
              <option v-for="o in boolOptions" :key="'imd'+o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <!-- RT / RW -->
          <div class="col-lg-2">
            <label class="form-label mb-1">RT</label>
            <input v-model="filterRT" type="text" class="form-control" placeholder="cth: 01" />
          </div>
          <div class="col-lg-2">
            <label class="form-label mb-1">RW</label>
            <input v-model="filterRW" type="text" class="form-control" placeholder="cth: 06" />
          </div>

          <!-- NIK length -->
          <div class="col-lg-4">
            <label class="form-label mb-1">Filter NIK</label>
            <select v-model="nikMode" class="form-select">
              <option v-for="o in nikOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <!-- Tombol Reset + Export: berdampingan & sama lebar -->
          <div class="col-lg-4 ms-auto">
            <div class="row g-2 justify-content-end">
              <div class="col-6">
                <button class="btn btn-danger w-100" @click="resetFilter">Reset</button>
              </div>
              <div class="col-6">
                <button
                  class="btn btn-warning w-100 d-inline-flex align-items-center justify-content-center"
                  @click="exportFilteredToExcel"
                  :disabled="loading || !filteredRows.length"
                >
                <font-awesome-icon :icon="['fas','file-export']" class="me-2" />
                  Export ({{ filteredRows.length }})
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Tabel preview (tanpa kolom Actions) -->
    <div class="card border-0 rounded shadow">
      <div class="card-body">
        <div v-if="loading" class="alert alert-info">Memuat…</div>
        <div v-else-if="errorMsg" class="alert alert-danger">{{ errorMsg }}</div>

        <template v-else>
          <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
            <span>Total data: {{ rows.length }}</span>
            <span>Ditampilkan: {{ filteredRows.length }}</span>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
              <thead class="bg-dark text-white">
                <tr>
                  <th scope="col">No</th>
                  <th scope="col">NIK</th>
                  <th scope="col">Anak Ke</th>
                  <th scope="col">Tgl Lahir</th>
                  <th scope="col">Jenis Kelamin</th>
                  <th scope="col">Nomor KK</th>
                  <th scope="col">Nama Anak</th>
                  <th scope="col">Usia Hamil</th>
                  <th scope="col">Berat Lahir</th>
                  <th scope="col">Panjang Lahir</th>
                  <th scope="col">Lingkar Kepala Lahir</th>
                  <th scope="col">KIA</th>
                  <th scope="col">KIA Bayi Kecil</th>
                  <th scope="col">IMD</th>
                  <th scope="col">Nama Ortu</th>
                  <th scope="col">NIK Ortu</th>
                  <th scope="col">HP Ortu</th>
                  <th scope="col">Posyandu</th>
                  <th scope="col">Desa</th>
                  <th scope="col">RT</th>
                  <th scope="col">RW</th>
                </tr>
              </thead>

              <tbody>
                <tr v-if="filteredRows.length === 0">
                  <td colspan="21" class="text-center">
                    <div class="alert alert-warning mb-0">Data tidak ditemukan.</div>
                  </td>
                </tr>

                <tr v-for="(item, index) in filteredRows" :key="item?.id ?? item?.nik ?? index">
                  <td>{{ index + 1 }}</td>
                  <td>{{ safe(item?.nik) }}</td>
                  <td>{{ safe(item?.anak_ke) }}</td>
                  <td>{{ fmtDate(item?.tgl_lahir) }}</td>
                  <td>{{ genderLabel(item?.jenis_kelamin) }}</td>
                  <td>{{ safe(item?.nomor_KK) }}</td>
                  <td>{{ safe(item?.nama_anak) }}</td>
                  <td>{{ safe(item?.usia_hamil) }}</td>
                  <td>{{ safe(item?.berat_lahir) }}</td>
                  <td>{{ safe(item?.panjang_lahir) }}</td>
                  <td>{{ safe(item?.lingkar_kepala_lahir) }}</td>
                  <td>{{ toYesNo(item?.kia) }}</td>
                  <td>{{ toYesNo(item?.kia_bayi_kecil) }}</td>
                  <td>{{ toYesNo(item?.imd) }}</td>
                  <td>{{ safe(item?.nama_ortu) }}</td>
                  <td>{{ safe(item?.nik_ortu) }}</td>
                  <td>{{ safe(item?.hp_ortu) }}</td>
                  <td>{{ posyanduLabel(item) }}</td>
                  <td>{{ getDesa(item) }}</td>
                  <td>{{ safe(item?.rt) }}</td>
                  <td>{{ safe(item?.rw) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style>
.swal2-actions-gap { gap: 0.5rem; }
</style>
