<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { posyanduLabelFromRow as posyanduLabel } from '../../utils/labels'
import api from '../../api'
import Swal from 'sweetalert2'
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

/* =================== STATE =================== */
const apk = ref([])
const loading = ref(false)
const errorMsg = ref('')

/* =================== FILTERS (sama dengan index + tambahan) =================== */
const q = ref('')                 // search bebas: NIK / Nama Anak / Label posyandu
const filterPosyandu = ref('')    // label util "Desa X - Posy. Y"
const tglFrom = ref('')           // yyyy-mm-dd
const tglTo = ref('')             // yyyy-mm-dd
const beratFrom = ref('')
const beratTo = ref('')
const tinggiFrom = ref('')
const tinggiTo = ref('')
const lilaFrom = ref('')
const lilaTo = ref('')
const lkFrom = ref('')
const lkTo = ref('')

// Tambahan:
const filterAsiMonths = ref([])   // array bulan ASI yang dipilih (0..6)
const filterVita = ref('')        // '', 'BIRU', 'MERAH', 'KOSONG'
const filterKelasIbu = ref('')    // '', 'YA', 'TIDAK'

/* =================== LOAD DATA =================== */
const fetchData = async () => {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await api.get('/anak-pengukuran', { headers: { Accept: 'application/json' } })
    apk.value = Array.isArray(res.data) ? res.data
      : Array.isArray(res.data?.data) ? res.data.data
      : Array.isArray(res.data?.data?.data) ? res.data.data.data
      : []
  } catch (e) {
    apk.value = []
    errorMsg.value = e?.response?.data?.message ?? 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}
onMounted(fetchData)

/* =================== NORMALISASI / OPTIONS =================== */
const rows = computed(() => {
  const u = apk.value
  if (Array.isArray(u)) return u
  if (Array.isArray(u?.data)) return u.data
  if (Array.isArray(u?.data?.data)) return u.data.data
  return []
})
const posyanduOptions = computed(() => {
  const uniq = new Set(rows.value.map(r => posyanduLabel(r)).filter(s => typeof s === 'string' && s.trim() !== ''))
  return Array.from(uniq).sort((a, b) => a.localeCompare(b, 'id'))
})

/* =================== HELPERS =================== */
const safe = (v) => (v ?? v === 0 ? v : '')

/* ===== Date-only helpers (tanpa timezone) ===== */
function _parseDateParts(value) {
  if (!value) return null
  if (value instanceof Date && !isNaN(value.getTime())) {
    return { y: value.getFullYear(), m: value.getMonth()+1, d: value.getDate() }
  }
  const s = String(value).trim()

  // 1) YYYY-MM-DD
  let m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (m) return { y:+m[1], m:+m[2], d:+m[3] }

  // 2) ISO dengan waktu/offset -> ambil tanggal
  m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T]/)
  if (m) return { y:+m[1], m:+m[2], d:+m[3] }

  // 3) DD/MM/YYYY atau DD-MM-YYYY
  m = s.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})$/)
  if (m) {
    let yy = +m[3]; if (yy < 100) yy += 2000
    return { y:yy, m:+m[2], d:+m[1] }
  }

  // 4) YYYY/MM/DD
  m = s.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/)
  if (m) return { y:+m[1], m:+m[2], d:+m[3] }

  return null
}
function _toYMD({ y, m, d }) {
  return `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`
}
function _ymdFromLocalDate(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
const fmtISO = (v) => {
  if (v == null || v === '') return ''
  if (v instanceof Date && !isNaN(v.getTime())) return _ymdFromLocalDate(v)

  const s = String(v).trim()

  // 1) Plain Y-M-D
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s

  // 2) D/M/Y atau D-M-Y
  let m = s.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})$/)
  if (m) {
    let yy = +m[3]; if (yy < 100) yy += 2000
    return `${yy}-${String(+m[2]).padStart(2,'0')}-${String(+m[1]).padStart(2,'0')}`
  }

  // 3) Y/M/D
  m = s.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/)
  if (m) return `${m[1]}-${String(+m[2]).padStart(2,'0')}-${String(+m[3]).padStart(2,'0')}`

  // 4) Ada jam / timezone? -> parse ke Date lalu ambil TANGGAL LOKAL
  if (/[T ]\d{2}:\d{2}/.test(s) || /Z|[+\-]\d{2}:?\d{2}$/.test(s)) {
    const d = new Date(s)
    if (!isNaN(d.getTime())) return _ymdFromLocalDate(d)
  }

  // 5) Fallback: coba Date lokal (tanpa ubah timezone), lalu ambil lokal
  const d2 = new Date(s.replace(' ', 'T'))
  if (!isNaN(d2.getTime())) return _ymdFromLocalDate(d2)

  // 6) Tidak bisa diparse, kembalikan apa adanya
  return s
}
// Rentang tanggal berbasis string Y-M-D (aman tanpa timezone)
function inDateRange(value, from, to) {
  const ymd = fmtISO(value); if (!ymd) return false
  const f = from ? fmtISO(from) : null
  const t = to   ? fmtISO(to)   : null
  if (f && ymd < f) return false
  if (t && ymd > t) return false
  return true
}

const parseNum = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : null }
const inNumRange = (val, from, to) => {
  const n = parseNum(val); if (n === null) return false
  const f = parseNum(from); const t = parseNum(to)
  if (f !== null && n < f) return false
  if (t !== null && n > t) return false
  return true
}

const fmtBool = (v) =>
  v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true'

const caraText = (r) => {
  const v = String(getCaraUkur(r) || '').toLowerCase()
  if (!v) return ''
  if (v === 'terlentang') return 'Terlentang'
  if (v === 'berdiri') return 'Berdiri'
  return getCaraUkur(r) || ''
}

function asiTagList(r) {
  const out = []
  for (let i = 0; i <= 6; i++) {
    const raw = getAsi(r, i)
    const on = raw === true || raw === 1 || raw === '1' || String(raw).toLowerCase() === 'true'
    if (on) out.push(i + 1) // tampil "Bulan 1..7"
  }
  return out
}

function fmtVita(v) {
  const val = v ?? ''
  if (typeof val === 'string') {
    const low = val.toLowerCase()
    if (low === 'biru' || low === 'merah') return low[0].toUpperCase() + low.slice(1)
    return val
  }
  return fmtBool(val) ? 'Ya' : ''
}

// Export: bila bukan "Biru/Merah" jadikan kosong
function toExportVitaValue(raw) {
  const s = String(raw ?? '').trim().toLowerCase()
  return (s === 'biru' || s === 'merah') ? s[0].toUpperCase() + s.slice(1) : ''
}
// Cek bulan vit A hanya dari string tanggal (tanpa Date())
function vitaAllowedOnDate(tgl) {
  const iso = fmtISO(tgl)
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return false
  const m = +iso.slice(5,7)
  return m === 2 || m === 8
}

// Normalisasi untuk filter Vitamin A (khusus warna)
const vitaColor = (v) => {
  const s = String(v ?? '').trim().toLowerCase()
  if (s === 'biru') return 'BIRU'
  if (s === 'merah') return 'MERAH'
  return '' // selain itu dianggap bukan warna (termasuk boolean true)
}

const toOneBlank = (v) => (fmtBool(v) ? 1 : '')

/* --- extractor aman untuk field yang mungkin beda nama di respons --- */
const get = (o, ...paths) => {
  for (const p of paths) {
    const v = p.split('.').reduce((acc, k) => (acc && acc[k] !== undefined ? acc[k] : undefined), o)
    if (v !== undefined && v !== null && v !== '') return v
  }
  return ''
}
const getNIK = r => String(get(r, 'nik_anak','nik','anak.nik') || '')
const getNamaAnak = r => String(get(r, 'anak.nama_anak', 'nama_anak') || '')
const getTglUkur = r => get(r, 'tanggal_ukur', 'tgl_ukur', 'tanggalUkur')
const getBerat = r => get(r, 'berat','bb')
const getTinggi = r => get(r, 'tinggi','tb')
const getLILA = r => get(r, 'lila','lila_cm')
const getLK = r => get(r, 'lingkar_kepala','lk')
const getCaraUkur = r => get(r, 'cara_ukur','caraUkur','caraukur')
const getVita = r => get(r, 'vita','vit_a','vita_a')
const getAsi = (r, i) => get(r, `asi_bulan_${i}`, `asi.bulan_${i}`)
const getKelasIbuBalita = r => get(r, 'kelas_ibu_balita','kelasIbuBalita')

/* =================== FILTER UTAMA =================== */
const filteredRows = computed(() => {
  const keyword = q.value.trim().toLowerCase()
  const selAsi = filterAsiMonths.value // array 0..6
  const selVita = filterVita.value     // '', 'BIRU','MERAH','KOSONG'
  const selKelas = filterKelasIbu.value// '', 'YA','TIDAK'

  return rows.value.filter(item => {
    const label = posyanduLabel(item) || ''

    // Posyandu
    if (filterPosyandu.value && label !== filterPosyandu.value) return false

    // Tanggal Ukur (date-only compare)
    if ((tglFrom.value || tglTo.value) &&
        !inDateRange(getTglUkur(item), tglFrom.value, tglTo.value)) return false

    // Rentang angka
    if ((beratFrom.value || beratTo.value) &&
        !inNumRange(getBerat(item), beratFrom.value, beratTo.value)) return false
    if ((tinggiFrom.value || tinggiTo.value) &&
        !inNumRange(getTinggi(item), tinggiFrom.value, tinggiTo.value)) return false
    if ((lilaFrom.value || lilaTo.value) &&
        !inNumRange(getLILA(item), lilaFrom.value, lilaTo.value)) return false
    if ((lkFrom.value || lkTo.value) &&
        !inNumRange(getLK(item), lkFrom.value, lkTo.value)) return false

    // === Filter tambahan ===

    // ASI: minimal salah satu bulan yang dipilih bernilai true
    if (Array.isArray(selAsi) && selAsi.length > 0) {
      const anyOn = selAsi.some(mIdx => fmtBool(getAsi(item, mIdx)))
      if (!anyOn) return false
    }

    // Vitamin A: warna spesifik atau kosong
    if (selVita) {
      const color = vitaColor(getVita(item))
      if (selVita === 'KOSONG') {
        // kosong = tidak ada warna & bukan true boolean
        const isTruthy = fmtBool(getVita(item))
        if (isTruthy || color !== '') return false
      } else {
        if (color !== selVita) return false
      }
    }

    // Kelas Ibu
    if (selKelas === 'YA' && !fmtBool(getKelasIbuBalita(item))) return false
    if (selKelas === 'TIDAK' && fmtBool(getKelasIbuBalita(item))) return false

    // Keyword
    if (!keyword) return true
    const nikStr = getNIK(item).toLowerCase()
    const namaStr = getNamaAnak(item).toLowerCase()
    const labelStr = String(label).toLowerCase()
    return nikStr.includes(keyword) || namaStr.includes(keyword) || labelStr.includes(keyword)
  })
})

/* =================== PENAMAAN FILE: prioritas Posyandu =================== */
function parsePosyFromLabel(label, sampleRows = []) {
  if (!label) return { desa:'', pos:'' }
  const m = label.match(/Desa\s*(.+?)\s*-\s*Posy\.\s*(.+)$/i)
  if (m) return { desa: (m[1]||'').trim(), pos: (m[2]||'').trim() }
  const it = sampleRows.find(r => (posyanduLabel(r) || '') === label)
  return { desa: it?.posyandu?.desa || '', pos: it?.posyandu?.nama || '' }
}

/* ========= Majority Year dari tanggal_ukur ========= */
function extractYear(val) {
  if (!val) return null
  const p = _parseDateParts(val)
  if (p) return p.y
  const s = String(val).trim()
  const m = s.match(/(\d{4})/)
  if (m) {
    const y = +m[1]
    if (y >= 1900 && y <= 2100) return y
  }
  return null
}
function majorityYear(list, getDateFn, tglFrom, tglTo) {
  const counter = new Map()
  for (const r of list || []) {
    const y = extractYear(getDateFn(r))
    if (y) counter.set(y, (counter.get(y) || 0) + 1)
  }
  if (counter.size > 0) {
    let max = -1, winners = []
    for (const [y, c] of counter) {
      if (c > max) { max = c; winners = [y] }
      else if (c === max) winners.push(y)
    }
    return Math.max(...winners)
  }
  const yf = extractYear(tglFrom); if (yf) return yf
  const yt = extractYear(tglTo);   if (yt) return yt
  return new Date().getFullYear()
}
function buildFileNameUK({ labelPos, sample, data, getTglUkur, tglFrom, tglTo }) {
  const src = (Array.isArray(data) && data.length) ? data : (Array.isArray(sample) ? sample : [])
  const year = majorityYear(src, getTglUkur, tglFrom, tglTo)
  const base = `UK ${year}`
  if (labelPos && String(labelPos).trim()) {
    const { desa, pos } = parsePosyFromLabel(labelPos, src.length ? src : sample)
    const desaFull = String(desa || '').trim().toUpperCase()
    const posFull  = String(pos  || '').trim().toUpperCase()
    return `UK ${desaFull}_${posFull}_${year}.xlsx`
  }
  return `${base}.xlsx`
}

/* =================== EXPORT EXCEL =================== */
function autoFitColumns(ws) {
  ws.columns.forEach(col => {
    let max = 10
    col.eachCell({ includeEmpty: true }, c => {
      const v = c.value == null ? '' : c.value.richText ? c.value.richText.map(t => t.text).join('') : String(c.value)
      max = Math.max(max, v.length + 2)
    })
    col.width = Math.min(Math.max(max, 10), 60)
  })
}

async function exportUKToExcel() {
  if (!filteredRows.value.length) {
    await Swal.fire('Tidak ada', 'Tidak ada data sesuai filter untuk diekspor.', 'info')
    return
  }

  const fname = buildFileNameUK({
    labelPos: filterPosyandu.value || '',
    sample: rows.value,
    data: filteredRows.value,
    getTglUkur: (r) => getTglUkur(r),
    tglFrom: tglFrom.value,
    tglTo:   tglTo.value,
  })

  const { isConfirmed } = await Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Ekspor',
    html: `
      <div class="text-start">
        <div>Jumlah baris: <b>${filteredRows.value.length}</b></div>
        ${filterPosyandu.value ? `<div>Filter Posyandu: <span class="badge bg-dark">${filterPosyandu.value}</span></div>` : ''}
        <div class="mt-2">Nama file:</div>
        <code>${fname}</code>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Ekspor',
    cancelButtonText: 'Batal',
  })
  if (!isConfirmed) return

  const header = [
    'No','NIK','nama_anak','TANGGALUKUR','BERAT','TINGGI','LILA','lingkar_kepala',
    'CARAUKUR','vita','asi_bulan_0','asi_bulan_1','asi_bulan_2','asi_bulan_3',
    'asi_bulan_4','asi_bulan_5','asi_bulan_6','kelas_ibu_balita'
  ]

  const dataRows = filteredRows.value.map((r, i) => {
    const tgl = fmtISO(getTglUkur(r))                   // date-only
    const vitaCell = vitaAllowedOnDate(tgl) ? toExportVitaValue(getVita(r)) : '' // kosong jika bukan bulan vitA

    return [
      i + 1,
      getNIK(r) || '-/(BELUM ADA)',
      getNamaAnak(r) || '',
      tgl,
      safe(getBerat(r)),
      safe(getTinggi(r)),
      safe(getLILA(r)),
      safe(getLK(r)),
      getCaraUkur(r) || '',
      vitaCell,
      toOneBlank(getAsi(r,0)),
      toOneBlank(getAsi(r,1)),
      toOneBlank(getAsi(r,2)),
      toOneBlank(getAsi(r,3)),
      toOneBlank(getAsi(r,4)),
      toOneBlank(getAsi(r,5)),
      toOneBlank(getAsi(r,6)),
      toOneBlank(getKelasIbuBalita(r)),
    ]
  })

  try {
    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('UK')

    ws.addRow(header)
    ws.getRow(1).eachCell((cell) => {
      cell.font = { bold: true }
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFF00' } }
      cell.alignment = { vertical: 'middle', horizontal: 'center' }
      cell.border = { top:{style:'thin'}, left:{style:'thin'}, bottom:{style:'thin'}, right:{style:'thin'} }
    })

    dataRows.forEach(r => ws.addRow(r))

    ws.eachRow({ includeEmpty: false }, (row, rowNumber) => {
      if (rowNumber === 1) return
      row.eachCell((cell, col) => {
        // Kolom 2 (NIK) & 4 (TanggalUkur) dipaksa sebagai teks agar Excel tidak mengubah
        if (col === 2 || col === 4) {
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
    await Swal.fire('Berhasil', `Ekspor <b>${dataRows.length}</b> baris ke <b>${fname}</b>.`, 'success')
  } catch (e) {
    await Swal.fire('Gagal ekspor', e?.message ?? 'Terjadi kesalahan saat membuat file Excel.', 'error')
  }
}

/* =================== RESET =================== */
const resetFilter = () => {
  q.value = ''
  filterPosyandu.value = ''
  tglFrom.value = ''
  tglTo.value = ''
  beratFrom.value = ''; beratTo.value = ''
  tinggiFrom.value = ''; tinggiTo.value = ''
  lilaFrom.value = ''; lilaTo.value = ''
  lkFrom.value = ''; lkTo.value = ''

  // Tambahan:
  filterAsiMonths.value = []
  filterVita.value = ''
  filterKelasIbu.value = ''
}
</script>

<template>
  <div class="container mt-5 mb-5">
    <!-- Header kiri: Kembali + Judul berdampingan -->
    <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
      <RouterLink
        :to="{ name: 'anak-pengukuran.index' }"
        class="btn btn-md btn-secondary rounded shadow border-0 d-inline-flex align-items-center"
      >
        ← Kembali
      </RouterLink>
      <h5 class="mb-0">Export Data Pengukuran (UK)</h5>
    </div>

    <!-- Filters -->
    <div class="card border-0 rounded shadow mb-3">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <!-- Search -->
          <div class="col-xl-4">
            <label class="form-label mb-1">Cari (NIK / Nama Anak / Desa / Posyandu)</label>
            <input v-model="q" type="text" class="form-control" placeholder="Ketik minimal 2 karakter…" />
          </div>

          <!-- Tanggal Ukur -->
          <div class="col-xl-4">
            <label class="form-label mb-1 d-block">Tanggal Ukur (Dari–Sampai)</label>
            <div class="input-group">
              <input v-model="tglFrom" type="date" class="form-control" />
              <span class="input-group-text">s.d.</span>
              <input v-model="tglTo" type="date" class="form-control" />
            </div>
          </div>

          <!-- Desa & Posyandu (label util) -->
          <div class="col-xl-4">
            <label class="form-label mb-1">Desa & Posyandu</label>
            <select v-model="filterPosyandu" class="form-select" :class="{ 'text-placeholder': !filterPosyandu }">
              <option value="">— Semua Desa & Posyandu —</option>
              <option v-for="opt in posyanduOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>

          <!-- Rentang numerik -->
          <div class="col-12">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label mb-1 d-block">Berat (kg)</label>
                <div class="input-group">
                  <input v-model="beratFrom" type="number" step="0.01" min="0" class="form-control" placeholder="min" />
                  <span class="input-group-text">s.d.</span>
                  <input v-model="beratTo" type="number" step="0.01" min="0" class="form-control" placeholder="max" />
                </div>
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1 d-block">Tinggi (cm)</label>
                <div class="input-group">
                  <input v-model="tinggiFrom" type="number" step="0.1" min="0" class="form-control" placeholder="min" />
                  <span class="input-group-text">s.d.</span>
                  <input v-model="tinggiTo" type="number" step="0.1" min="0" class="form-control" placeholder="max" />
                </div>
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1 d-block">LILA (cm)</label>
                <div class="input-group">
                  <input v-model="lilaFrom" type="number" step="0.1" min="0" class="form-control" placeholder="min" />
                  <span class="input-group-text">s.d.</span>
                  <input v-model="lilaTo" type="number" step="0.1" min="0" class="form-control" placeholder="max" />
                </div>
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1 d-block">Lingkar Kepala (cm)</label>
                <div class="input-group">
                  <input v-model="lkFrom" type="number" step="0.1" min="0" class="form-control" placeholder="min" />
                  <span class="input-group-text">s.d.</span>
                  <input v-model="lkTo" type="number" step="0.1" min="0" class="form-control" placeholder="max" />
                </div>
              </div>
            </div>
          </div>

          <!-- ====== Filter Tambahan: ASI / Vitamin A / Kelas Ibu ====== -->
          <div class="col-12">
            <div class="row g-3 align-items-end">
              <!-- ASI 0–6 (multi-pilih) -->
              <div class="col-xl-5">
                <label class="form-label mb-1 d-block">ASI 0–6 Bulan (pilih satu/lebih)</label>
                <div class="d-flex flex-wrap gap-2">
                  <label v-for="m in [0,1,2,3,4,5,6]" :key="m" class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :value="m"
                      v-model="filterAsiMonths"
                    />
                    <span class="form-check-label">Bulan {{ m }}</span>
                  </label>
                </div>
              </div>

              <!-- Vitamin A (warna) -->
              <div class="col-xl-3">
                <label class="form-label mb-1">Vitamin A</label>
                <select v-model="filterVita" class="form-select">
                  <option value="">— Semua —</option>
                  <option value="BIRU">Biru</option>
                  <option value="MERAH">Merah</option>
                  <option value="KOSONG">Kosong</option>
                </select>
                <div class="form-text">Hanya nilai warna (Februari/Agustus). “Kosong” = tidak terisi warna/boolean.</div>
              </div>

              <!-- Kelas Ibu -->
              <div class="col-xl-4">
                <label class="form-label mb-1">Kelas Ibu Balita</label>
                <select v-model="filterKelasIbu" class="form-select">
                  <option value="">— Semua —</option>
                  <option value="YA">Ya</option>
                  <option value="TIDAK">Tidak</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Tombol Reset & Export -->
          <div class="col-xl-4 ms-auto">
            <div class="row g-2 justify-content-end">
              <div class="col-6">
                <button class="btn btn-danger w-100" @click="resetFilter">Reset</button>
              </div>
              <div class="col-6">
                <button
                  class="btn btn-warning w-100 d-inline-flex align-items-center justify-content-center"
                  @click="exportUKToExcel"
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

    <!-- Tabel (preview sederhana: tetap tabel indexmu) -->
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
                  <th>No</th>
                  <th>NIK</th>
                  <th>Nama Anak</th>
                  <th>Desa & Posyandu</th>
                  <th>Tanggal Ukur</th>
                  <th>Berat (kg)</th>
                  <th>Tinggi (cm)</th>
                  <th>LILA (cm)</th>
                  <th>Lingkar Kepala (cm)</th>
                  <th>Cara Ukur</th>
                  <th>Asi 0–6 Bulan</th>
                  <th>Vitamin A</th>
                  <th>Kelas Ibu Balita</th>
                </tr>
              </thead>

              <tbody>
                <tr v-if="filteredRows.length === 0">
                  <td colspan="13" class="text-center">
                    <div class="alert alert-warning mb-0">Data tidak ditemukan.</div>
                  </td>
                </tr>

                <tr v-for="(item, index) in filteredRows" :key="getNIK(item) || index">
                  <td>{{ index + 1 }}</td>
                  <td>{{ getNIK(item) || '-/(BELUM ADA)' }}</td>
                  <td>{{ getNamaAnak(item) || '-' }}</td>
                  <td>{{ posyanduLabel(item) || '-' }}</td>
                  <td>{{ fmtISO(getTglUkur(item)) || '-' }}</td>
                  <td>{{ safe(getBerat(item)) || '-' }} kg</td>
                  <td>{{ safe(getTinggi(item)) || '-' }} cm</td>
                  <td>{{ safe(getLILA(item)) || '-' }}</td>
                  <td>{{ safe(getLK(item)) || '-' }}</td>

                  <td>{{ caraText(item) || '-' }}</td>

                  <td>
                    <template v-if="asiTagList(item).length">
                      <span
                        v-for="bulan in asiTagList(item)"
                        :key="bulan"
                        class="badge bg-success me-1 mb-1"
                      >
                        <font-awesome-icon :icon="['fas','check']" class="me-1" />
                        Bulan {{ bulan }}
                      </span>
                    </template>
                    <span v-else class="text-muted">—</span>
                  </td>

                  <td>{{ fmtVita(getVita(item)) || '-' }}</td>

                  <td>
                    <span class="badge" :class="fmtBool(getKelasIbuBalita(item)) ? 'bg-success' : 'bg-secondary'">
                      {{ fmtBool(getKelasIbuBalita(item)) ? 'Ya' : '–' }}
                    </span>
                  </td>
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
.swal2-actions-gap { gap: .5rem; }
.text-placeholder { color: #6c757d; }
</style>
