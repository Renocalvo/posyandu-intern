<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { posyanduLabelFromRow as posyanduLabel } from '../../utils/labels'
import api from '../../api'
import Swal from 'sweetalert2'

/* =================== STATE =================== */
const apk = ref([])
const loading = ref(false)
const errorMsg = ref('')
const dataFromSession = ref(false)

/* =================== PAGINATION & CACHE CONFIG =================== */
const PAGE_SIZE = 20
const currentPage = ref(1)
const totalItems = ref(0)
const loadedPages = ref(new Set())
const CACHE_EXPIRY = 30 * 60 * 1000

/* =================== LOG CACHE (semua log, di-fetch sekali) =================== */
const allLogsMap = ref({}) // { [nik]: [...logs] }
const logsLoaded = ref(false)
const logsLoading = ref(false)

/* =================== FILTERS =================== */
const q = ref('')
const filterPosyandu = ref('')
const tglFrom = ref('')
const tglTo = ref('')
const beratFrom = ref(''); const beratTo = ref('')
const tinggiFrom = ref(''); const tinggiTo = ref('')
const lilaFrom = ref(''); const lilaTo = ref('')
const lkFrom = ref(''); const lkTo = ref('')
const filterAsiMonths = ref([])
const filterVita = ref('')
const filterKelasIbu = ref('')
const showAnomaliesOnly = ref(false)

/* =================== CACHE MANAGEMENT =================== */
const getCacheKey = (key) => `anakPengukuran_${key}`

const getFromCache = (key) => {
  try {
    const cached = sessionStorage.getItem(getCacheKey(key))
    if (!cached) return null
    const { data, timestamp } = JSON.parse(cached)
    if (Date.now() - timestamp > CACHE_EXPIRY) {
      sessionStorage.removeItem(getCacheKey(key))
      return null
    }
    return data
  } catch (e) {
    return null
  }
}

const saveToCache = (key, data) => {
  try {
    sessionStorage.setItem(getCacheKey(key), JSON.stringify({ data, timestamp: Date.now() }))
  } catch (e) {}
}

const clearCache = () => {
  sessionStorage.removeItem(getCacheKey('data'))
  sessionStorage.removeItem(getCacheKey('meta'))
  sessionStorage.removeItem(getCacheKey('logs'))
  loadedPages.value.clear()
  apk.value = []
  currentPage.value = 1
  dataFromSession.value = false
  allLogsMap.value = {}
  logsLoaded.value = false
  rowsWithAnomaly.value = []
}

/* =================== FETCH SEMUA LOG SEKALIGUS =================== */
const fetchAllLogs = async () => {
  // Cek cache logs dulu
  const cachedLogs = getFromCache('logs')
  if (cachedLogs) {
    allLogsMap.value = cachedLogs
    logsLoaded.value = true
    return
  }

  logsLoading.value = true
  try {
    const res = await api.get('/log-pengukuran', {
      headers: { Accept: 'application/json' }
    })

    const list = Array.isArray(res.data)
      ? res.data
      : Array.isArray(res.data?.data)
        ? res.data.data
        : []

    // Group by NIK
    const map = {}
    for (const log of list) {
      const nik = log.nik_log
      if (!nik) continue
      if (!map[nik]) map[nik] = []
      map[nik].push(log)
    }

    // Sort tiap NIK by tanggal_ukur_lama desc
    for (const nik in map) {
      map[nik].sort((a, b) =>
        new Date(b.tanggal_ukur_lama || 0) - new Date(a.tanggal_ukur_lama || 0)
      )
    }

    allLogsMap.value = map
    logsLoaded.value = true
    saveToCache('logs', map)
  } catch (e) {
    console.error('Gagal fetch logs:', e)
  } finally {
    logsLoading.value = false
  }
}

/* =================== LOAD DATA =================== */
const fetchData = async (page = 1) => {
  if (loadedPages.value.has(page)) return

  loading.value = true
  errorMsg.value = ''

  try {
    const response = await api.get('/anak-pengukuran', {
      params: { page, per_page: PAGE_SIZE },
      headers: { Accept: 'application/json' }
    })

    let items = []
    let meta = {}

    if (response.data?.data) {
      items = Array.isArray(response.data.data) ? response.data.data : []
      meta = response.data.meta || {
        total: response.data.total || 0,
        per_page: PAGE_SIZE,
        current_page: page,
        last_page: Math.ceil((response.data.total || 0) / PAGE_SIZE)
      }
    } else if (Array.isArray(response.data)) {
      items = response.data
    }

    const startIndex = (page - 1) * PAGE_SIZE
    const newApk = [...apk.value]
    items.forEach((item, idx) => { newApk[startIndex + idx] = item })

    apk.value = newApk
    totalItems.value = meta.total || items.length
    loadedPages.value.add(page)

    saveToCache('data', apk.value)
    saveToCache('meta', meta)
    dataFromSession.value = false

  } catch (e) {
    errorMsg.value = e?.response?.data?.message ?? 'Gagal memuat data.'
    apk.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const cachedData = getFromCache('data')
  const cachedMeta = getFromCache('meta')

  if (cachedData && cachedMeta) {
    apk.value = cachedData
    totalItems.value = cachedMeta.total || cachedData.length
    loadedPages.value.add(1)
    dataFromSession.value = true
  }

  // Fetch data pengukuran & semua log secara paralel
  await Promise.all([
    loadedPages.value.has(1) ? Promise.resolve() : fetchData(1),
    fetchAllLogs()
  ])

  enrichDataWithAnomaly()
})

const resetCache = () => {
  clearCache()
  Promise.all([fetchData(1), fetchAllLogs()]).then(() => enrichDataWithAnomaly())
}

/* =================== PAGINATION =================== */
const goToPage = async (page) => {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
  if (!loadedPages.value.has(page)) {
    await fetchData(page)
  }
  enrichDataWithAnomaly()
}

const totalPages = computed(() => Math.ceil(totalItems.value / PAGE_SIZE))

/* =================== NORMALISASI =================== */
const rows = computed(() => {
  const u = apk.value
  if (Array.isArray(u)) return u
  if (Array.isArray(u?.data)) return u.data
  if (Array.isArray(u?.data?.data)) return u.data.data
  return []
})

const pageData = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE
  return rows.value.slice(start, start + PAGE_SIZE)
})

const posyanduOptions = computed(() => {
  const uniq = new Set(rows.value.map(r => posyanduLabel(r)).filter(s => typeof s === 'string' && s.trim() !== ''))
  return Array.from(uniq).sort((a, b) => a.localeCompare(b, 'id'))
})

/* =================== HELPERS =================== */
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
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s

  let m = s.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})$/)
  if (m) {
    let yy = +m[3]
    if (yy < 100) yy += 2000
    return `${yy}-${String(+m[2]).padStart(2,'0')}-${String(+m[1]).padStart(2,'0')}`
  }

  m = s.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/)
  if (m) return `${m[1]}-${String(+m[2]).padStart(2,'0')}-${String(+m[3]).padStart(2,'0')}`

  if (/[T ]\d{2}:\d{2}/.test(s) || /Z|[+\-]\d{2}:?\d{2}$/.test(s)) {
    const d = new Date(s)
    if (!isNaN(d.getTime())) return _ymdFromLocalDate(d)
  }

  const d2 = new Date(s.replace(' ', 'T'))
  if (!isNaN(d2.getTime())) return _ymdFromLocalDate(d2)
  return s
}

const toDisplayDate = (v) => {
  const iso = fmtISO(v)
  if (!iso) return '-'
  const [y,m,d] = iso.split('-')
  return `${d}/${m}/${y}`
}

function inDateRange(value, from, to) {
  const ymd = fmtISO(value)
  if (!ymd) return false
  const f = from ? fmtISO(from) : null
  const t = to ? fmtISO(to) : null
  if (f && ymd < f) return false
  if (t && ymd > t) return false
  return true
}

const safe = (v) => (v ?? v === 0 ? v : '')
const fmtBool = (v) => v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true'
const parseNum = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : null }

const inNumRange = (val, from, to) => {
  const n = parseNum(val)
  if (n === null) return false
  const f = parseNum(from); const t = parseNum(to)
  if (f !== null && n < f) return false
  if (t !== null && n > t) return false
  return true
}

function getAsi(item, i) {
  const k1 = `asi_bulan_${i}`
  const k2 = item?.asi ? item.asi[`bulan_${i}`] : undefined
  return item?.[k1] ?? k2
}

function asiTagList(item) {
  const out = []
  for (let i = 0; i <= 6; i++) {
    const raw = getAsi(item, i)
    const on = raw === true || raw === 1 || raw === '1' || String(raw).toLowerCase?.() === 'true'
    if (on) out.push(i + 1)
  }
  return out
}

function getVita(item) { return item?.vita ?? item?.vit_a ?? item?.vita_a ?? '' }
const vitaColor = (v) => {
  const s = String(v ?? '').trim().toLowerCase()
  if (s === 'biru') return 'BIRU'
  if (s === 'merah') return 'MERAH'
  return ''
}
const getKelasIbu = (item) => item?.kelas_ibu_balita ?? item?.kelasIbuBalita

/* =================== ANOMALY DETECTION - PAKAI allLogsMap =================== */
const rowsWithAnomaly = ref([])

const detectAnomalyFromMap = (item) => {
  const nik = item?.anak?.nik
  if (!nik) return { hasAnomaly: false, type: [], details: {} }

  const logData = allLogsMap.value[nik]
  if (!logData || logData.length === 0) return { hasAnomaly: false, type: [], details: {} }

  const prevData = logData[0] // sudah di-sort desc, jadi [0] = terbaru
  const currData = item

  const anomalies = []
  const details = {
    prev: {
      tinggi: prevData.tinggi_lama,
      berat: prevData.berat_lama,
      lila: prevData.lila_lama,
      lingkar_kepala: prevData.lingkar_kepala_lama,
      tanggal_ukur: prevData.tanggal_ukur_lama
    },
    curr: {
      tinggi: currData.tinggi,
      berat: currData.berat,
      lila: currData.lila,
      lingkar_kepala: currData.lingkar_kepala,
      tanggal_ukur: currData.tanggal_ukur
    },
    diff: {}
  }

  if (currData.tinggi && prevData.tinggi_lama) {
    const d = currData.tinggi - prevData.tinggi_lama
    details.diff.tinggi = d
    if (d < -0.5) anomalies.push('tinggi_menurun')
    else if (d > 5) anomalies.push('tinggi_melonjak')
  }
  if (currData.berat && prevData.berat_lama) {
    const d = currData.berat - prevData.berat_lama
    details.diff.berat = d
    if (d < -1) anomalies.push('berat_turun_drastis')
    else if (d > 3) anomalies.push('berat_naik_drastis')
  }
  if (currData.lila && prevData.lila_lama) {
    const d = currData.lila - prevData.lila_lama
    details.diff.lila = d
    if (d < -1) anomalies.push('lila_menurun')
  }
  if (currData.lingkar_kepala && prevData.lingkar_kepala_lama) {
    const d = currData.lingkar_kepala - prevData.lingkar_kepala_lama
    details.diff.lingkar_kepala = d
    if (d < -0.5) anomalies.push('lk_menurun')
    else if (d > 3) anomalies.push('lk_melonjak')
  }

  return { hasAnomaly: anomalies.length > 0, type: anomalies, details }
}

const enrichDataWithAnomaly = () => {
  if (!logsLoaded.value) return // tunggu logs selesai di-fetch

  rowsWithAnomaly.value = pageData.value.map(item => ({
    ...item,
    anomalyInfo: detectAnomalyFromMap(item)
  }))
}

/* =================== FILTERING =================== */
const filteredRows = computed(() => {
  const keyword = q.value.trim().toLowerCase()
  const selAsi = filterAsiMonths.value

  return rowsWithAnomaly.value.filter(item => {
    const label = posyanduLabel(item) || ''

    if (showAnomaliesOnly.value && !item.anomalyInfo?.hasAnomaly) return false
    if (filterPosyandu.value && label !== filterPosyandu.value) return false
    if ((tglFrom.value || tglTo.value) && !inDateRange(item?.tanggal_ukur, tglFrom.value, tglTo.value)) return false
    if ((beratFrom.value || beratTo.value) && !inNumRange(item?.berat, beratFrom.value, beratTo.value)) return false
    if ((tinggiFrom.value || tinggiTo.value) && !inNumRange(item?.tinggi, tinggiFrom.value, tinggiTo.value)) return false
    if ((lilaFrom.value || lilaTo.value) && !inNumRange(item?.lila, lilaFrom.value, lilaTo.value)) return false
    if ((lkFrom.value || lkTo.value) && !inNumRange(item?.lingkar_kepala, lkFrom.value, lkTo.value)) return false

    if (Array.isArray(selAsi) && selAsi.length > 0) {
      const anyOn = selAsi.some(mIdx => {
        const raw = getAsi(item, mIdx)
        return raw === true || raw === 1 || raw === '1' || String(raw).toLowerCase?.() === 'true'
      })
      if (!anyOn) return false
    }

    if (filterVita.value) {
      const color = vitaColor(getVita(item))
      if (filterVita.value === 'KOSONG') {
        if (fmtBool(getVita(item)) || color !== '') return false
      } else {
        if (color !== filterVita.value) return false
      }
    }

    if (filterKelasIbu.value === 'YA' && !fmtBool(getKelasIbu(item))) return false
    if (filterKelasIbu.value === 'TIDAK' && fmtBool(getKelasIbu(item))) return false

    if (keyword) {
      const nikStr = String(item?.anak?.nik ?? '').toLowerCase()
      const namaStr = String(item?.anak?.nama_anak ?? '').toLowerCase()
      const labelStr = String(label).toLowerCase()
      return nikStr.includes(keyword) || namaStr.includes(keyword) || labelStr.includes(keyword)
    }

    return true
  })
})

const anomalyCount = computed(() =>
  rowsWithAnomaly.value.filter(item => item.anomalyInfo?.hasAnomaly).length
)

const getAnomalyBadgeClass = (types) => {
  if (!types?.length) return ''
  const criticalTypes = ['tinggi_menurun', 'berat_turun_drastis', 'lila_menurun', 'lk_menurun']
  return types.some(t => criticalTypes.includes(t)) ? 'bg-danger' : 'bg-warning'
}

const getAnomalyLabel = (type) => ({
  'tinggi_menurun': 'Tinggi Menurun',
  'tinggi_melonjak': 'Tinggi Naik Drastis',
  'berat_turun_drastis': 'Berat Turun Drastis',
  'berat_naik_drastis': 'Berat Naik Drastis',
  'lila_menurun': 'LILA Menurun',
  'lk_menurun': 'LK Menurun',
  'lk_melonjak': 'LK Naik Drastis'
})[type] || type

/* =================== SHOW DATA COMPARISON =================== */
const showDataComparison = async (item) => {
  if (!item.anomalyInfo?.hasAnomaly) {
    await Swal.fire({ title: 'Tidak Ada Anomali', text: 'Data pengukuran ini tidak memiliki anomali.', icon: 'info', confirmButtonText: 'Tutup' })
    return
  }

  const { details, type } = item.anomalyInfo
  const { prev, curr, diff } = details

  const anomalyBadges = type.map(t =>
    `<span class="badge ${getAnomalyBadgeClass([t])} me-1">${getAnomalyLabel(t)}</span>`
  ).join(' ')

  const formatDiff = (val) => {
    if (val == null) return '-'
    const num = parseFloat(val)
    if (!Number.isFinite(num)) return '-'
    return `${num >= 0 ? '+' : ''}${num.toFixed(2)}`
  }

  const diffColor = (val) => {
    if (val == null) return ''
    const num = parseFloat(val)
    if (!Number.isFinite(num)) return ''
    return num < 0 ? 'text-danger' : num > 0 ? 'text-success' : 'text-muted'
  }

  const result = await Swal.fire({
    title: '⚠️ Deteksi Anomali Pengukuran',
    html: `
      <div class="text-start">
        <div class="mb-3">
          <div><strong>Nama Anak:</strong> ${item?.anak?.nama_anak || '-'}</div>
          <div><strong>NIK:</strong> <code>${item?.anak?.nik || '-'}</code></div>
        </div>
        <div class="alert alert-warning mb-3">
          <strong>Anomali Terdeteksi:</strong><br>${anomalyBadges}
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header bg-secondary text-white"><strong>Data Sebelumnya</strong></div>
              <div class="card-body">
                <table class="table table-sm mb-0">
                  <tr><td>Tanggal Ukur:</td><td><strong>${toDisplayDate(prev.tanggal_ukur)}</strong></td></tr>
                  <tr><td>Tinggi Badan:</td><td><strong>${prev.tinggi || '-'} cm</strong></td></tr>
                  <tr><td>Berat Badan:</td><td><strong>${prev.berat || '-'} kg</strong></td></tr>
                  <tr><td>LILA:</td><td><strong>${prev.lila || '-'} cm</strong></td></tr>
                  <tr><td>Lingkar Kepala:</td><td><strong>${prev.lingkar_kepala || '-'} cm</strong></td></tr>
                </table>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header bg-primary text-white"><strong>Data Terbaru</strong></div>
              <div class="card-body">
                <table class="table table-sm mb-0">
                  <tr><td>Tanggal Ukur:</td><td><strong>${toDisplayDate(curr.tanggal_ukur)}</strong></td></tr>
                  <tr><td>Tinggi Badan:</td><td><strong>${curr.tinggi || '-'} cm</strong> <span class="${diffColor(diff.tinggi)} ms-2">${formatDiff(diff.tinggi)}</span></td></tr>
                  <tr><td>Berat Badan:</td><td><strong>${curr.berat || '-'} kg</strong> <span class="${diffColor(diff.berat)} ms-2">${formatDiff(diff.berat)}</span></td></tr>
                  <tr><td>LILA:</td><td><strong>${curr.lila || '-'} cm</strong> <span class="${diffColor(diff.lila)} ms-2">${formatDiff(diff.lila)}</span></td></tr>
                  <tr><td>Lingkar Kepala:</td><td><strong>${curr.lingkar_kepala || '-'} cm</strong> <span class="${diffColor(diff.lingkar_kepala)} ms-2">${formatDiff(diff.lingkar_kepala)}</span></td></tr>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4">
          <label class="form-label"><strong>Pilih data yang akan digunakan:</strong></label>
          <select id="data-pilihan" class="form-select">
            <option value="curr" selected>Gunakan Data Terbaru</option>
            <option value="prev">Gunakan Data Sebelumnya (Rollback)</option>
          </select>
        </div>
      </div>`,
    width: '900px',
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-save me-2"></i>Simpan Pilihan',
    cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
    customClass: { confirmButton: 'btn btn-primary mx-2', cancelButton: 'btn btn-secondary' },
    buttonsStyling: false,
    preConfirm: () => document.getElementById('data-pilihan').value
  })

  if (result.isConfirmed) {
    await updateDataSelection(item, result.value, details)
  }
}

/* =================== UPDATE DATA =================== */
const updateDataSelection = async (item, selectedData, details) => {
  try {
    const { prev, curr } = details
    const updatedData = {
      anak_id: item.anak_id || item.anak?.id,
      tanggal_ukur: selectedData === 'prev' ? prev.tanggal_ukur : curr.tanggal_ukur,
      tinggi: selectedData === 'prev' ? prev.tinggi : curr.tinggi,
      berat: selectedData === 'prev' ? prev.berat : curr.berat,
      lila: selectedData === 'prev' ? prev.lila : curr.lila,
      lingkar_kepala: selectedData === 'prev' ? prev.lingkar_kepala : curr.lingkar_kepala,
      posyandu_id: item.posyandu_id,
      cara_ukur: item.cara_ukur,
      vit_a: item.vit_a || item.vita || null,
      asi_bulan_0: getAsi(item, 0), asi_bulan_1: getAsi(item, 1),
      asi_bulan_2: getAsi(item, 2), asi_bulan_3: getAsi(item, 3),
      asi_bulan_4: getAsi(item, 4), asi_bulan_5: getAsi(item, 5),
      asi_bulan_6: getAsi(item, 6),
      kelas_ibu_balita: getKelasIbu(item)
    }

    await api.patch(`/anak-pengukuran/${item.id}`, updatedData, { headers: { Accept: 'application/json' } })

    await Swal.fire({
      title: 'Berhasil!',
      html: selectedData === 'prev'
        ? 'Data berhasil di-rollback ke pengukuran sebelumnya.'
        : 'Data terbaru dikonfirmasi dan disimpan.',
      icon: 'success',
      confirmButtonText: 'OK'
    })

    clearCache()
    await Promise.all([fetchData(1), fetchAllLogs()])
    enrichDataWithAnomaly()

  } catch (error) {
    await Swal.fire({
      title: 'Gagal Memperbarui',
      text: error?.response?.data?.message ?? 'Terjadi kesalahan.',
      icon: 'error',
      confirmButtonText: 'Tutup'
    })
  }
}

/* =================== VIEW RIWAYAT =================== */
const viewRiwayat = async (nik, nama = '') => {
  // Gunakan allLogsMap jika sudah loaded, tidak perlu fetch lagi
  const useCached = logsLoaded.value && allLogsMap.value[nik]

  Swal.fire({
    title: 'Riwayat Pengukuran',
    html: `<div class="mb-2"><b>${nama || '-'}</b></div><div class="small text-muted">NIK: <code>${nik || '-'}</code></div><div class="mt-2">Memuat data riwayat…</div>`,
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
    width: '1000px',
    buttonsStyling: false,
  })

  const asiBadges = (r) => {
    const flags = []
    for (let i = 0; i <= 6; i++) {
      const v = r?.[`asi_bulan_${i}_lama`]
      if (v === true || v === 1 || v === '1' || String(v).toLowerCase?.() === 'true') flags.push(i)
    }
    return flags.length ? flags.map(b => `<span class="badge bg-success me-1">Bulan ${b}</span>`).join(' ') : '—'
  }

  const posLabel = (r) => {
    const desa = r?.posyandu?.desa || r?.desa_lama || ''
    const nama = r?.posyandu?.nama || r?.posyandu_lama || ''
    if (!desa && !nama) return '—'
    return desa && nama ? `${desa} - Posy. ${nama}` : desa || nama
  }

  const safeBool = (v) => v === true || v === 1 || v === '1' || String(v).toLowerCase?.() === 'true'

  try {
    let list = []
    if (useCached) {
      list = allLogsMap.value[nik]
    } else {
      // Fallback: fetch individual jika map belum ada
      const res = await api.get(`/log-pengukuran/nik/${nik}`, { headers: { Accept: 'application/json' } })
      list = Array.isArray(res.data) ? res.data
        : Array.isArray(res.data?.data) ? res.data.data : []
    }

    list = [...list].sort((a, b) =>
      new Date(b.diubah_pada || b.updated_at || 0) - new Date(a.diubah_pada || a.updated_at || 0)
    )

    const headerInfo = `
      <div class="mb-3">
        <div class="mb-1"><span class="badge bg-secondary me-2">Nama</span><b>${nama || '-'}</b></div>
        <div><span class="badge bg-secondary me-2">NIK</span><code>${nik || '-'}</code></div>
      </div>`

    const rowsHtml = list.map((r, i) => {
      const waktuUbah = r.diubah_pada
        ? new Date(r.diubah_pada).toLocaleString('id-ID')
        : (r.updated_at ? new Date(r.updated_at).toLocaleString('id-ID') : '-')
      return `
        <tr>
          <td>${i + 1}</td>
          <td>${toDisplayDate(r.tanggal_ukur_lama || r.tanggal_ukur)}</td>
          <td>${posLabel(r)}</td>
          <td>${r.berat_lama ?? '-'}</td>
          <td>${r.tinggi_lama ?? '-'}</td>
          <td>${r.lila_lama ?? '-'}</td>
          <td>${r.lingkar_kepala_lama ?? '-'}</td>
          <td>${r.cara_ukur_lama || '-'}</td>
          <td>${r.vit_a_lama || '-'}</td>
          <td>${asiBadges(r)}</td>
          <td><span class="badge ${safeBool(r.kelas_ibu_balita_lama) ? 'bg-success' : 'bg-secondary'}">${safeBool(r.kelas_ibu_balita_lama) ? 'Ya' : 'Tidak'}</span></td>
          <td>${waktuUbah}</td>
        </tr>`
    }).join('')

    Swal.hideLoading()
    Swal.update({
      title: 'Riwayat Pengukuran',
      html: `${headerInfo}
        <div style="max-height:70vh;overflow:auto;">
          <div class="mb-2 small text-muted">Menampilkan ${list.length} entri riwayat.</div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped align-middle table-riwayat">
              <thead class="table-dark">
                <tr>
                  <th>No</th><th>Tanggal Ukur</th><th>Posyandu (lama)</th>
                  <th>Berat</th><th>Tinggi</th><th>LILA</th><th>Lingkar Kepala</th>
                  <th>Cara Ukur</th><th>Vitamin A</th><th>ASI 0–6</th><th>Kelas Ibu</th><th>Diubah pada</th>
                </tr>
              </thead>
              <tbody>${rowsHtml || '<tr><td colspan="12" class="text-center text-muted">Tidak ada riwayat.</td></tr>'}</tbody>
            </table>
          </div>
        </div>`,
      showCloseButton: true,
      showConfirmButton: true,
      confirmButtonText: 'Tutup',
      allowEscapeKey: true,
      width: '1200px',
      customClass: { popup: 'swal-riwayat', actions: 'swal2-actions-gap', confirmButton: 'btn btn-primary' },
      buttonsStyling: false,
    })
  } catch (e) {
    Swal.hideLoading()
    Swal.update({
      title: 'Riwayat Pengukuran',
      html: `<div class="mb-2"><b>${nama || '-'}</b></div>
             <div class="text-danger mt-2">${e?.response?.data?.message ?? 'Tidak dapat mengambil riwayat.'}</div>`,
      showCloseButton: true,
      showConfirmButton: true,
      confirmButtonText: 'Tutup',
      allowEscapeKey: true,
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    })
  }
}

/* =================== DELETE DATA =================== */
const deleteData = async (id) => {
  const { isConfirmed } = await Swal.fire({
    title: 'Hapus Data ini?',
    text: 'Tindakan ini tidak bisa dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary', actions: 'swal2-actions-gap' },
    buttonsStyling: false,
  })
  if (!isConfirmed) return

  try {
    await api.delete(`/anak-pengukuran/${id}`)
    await Swal.fire({ title: 'Terhapus', text: 'Data berhasil dihapus.', icon: 'success', timer: 1400, showConfirmButton: false })
    clearCache()
    await Promise.all([fetchData(1), fetchAllLogs()])
    enrichDataWithAnomaly()
  } catch (e) {
    await Swal.fire({ title: 'Gagal', text: e?.response?.data?.message ?? 'Gagal menghapus data.', icon: 'error' })
  }
}

/* =================== RESET FILTER =================== */
const resetFilter = () => {
  q.value = ''; filterPosyandu.value = ''
  tglFrom.value = ''; tglTo.value = ''
  beratFrom.value = ''; beratTo.value = ''
  tinggiFrom.value = ''; tinggiTo.value = ''
  lilaFrom.value = ''; lilaTo.value = ''
  lkFrom.value = ''; lkTo.value = ''
  filterAsiMonths.value = []
  filterVita.value = ''; filterKelasIbu.value = ''
  showAnomaliesOnly.value = false
}

/* =================== WATCH =================== */
watch(currentPage, async (newPage) => {
  if (!loadedPages.value.has(newPage)) await fetchData(newPage)
  enrichDataWithAnomaly()
})

// Begitu logs selesai dimuat, langsung enrich
watch(logsLoaded, (val) => {
  if (val) enrichDataWithAnomaly()
})
</script>
<template>
  <div class="container mt-5 mb-5">
    <div class="row"><div class="col-md-12">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <RouterLink :to="{ name: 'anak-pengukuran.create' }" class="btn btn-md btn-success rounded shadow border-0">
          + Tambah Data
        </RouterLink>
        <div class="d-flex align-items-center flex-wrap gap-2">
          <RouterLink :to="{ name: 'anak-pengukuran.import' }" class="btn btn-md btn-primary rounded shadow border-0 d-inline-flex align-items-center">
            <font-awesome-icon :icon="['fas','file-import']" class="me-2" />Import File
          </RouterLink>
          <RouterLink :to="{ name: 'anak-pengukuran.export' }" class="btn btn-md btn-warning rounded d-inline-flex align-items-center">
            <font-awesome-icon :icon="['fas','file-export']" class="me-2" />Export Data
          </RouterLink>
          <button v-if="dataFromSession" @click="resetCache" class="btn btn-md btn-info rounded d-inline-flex align-items-center">
            <font-awesome-icon :icon="['fas','sync-alt']" class="me-2" />Refresh
          </button>
        </div>
      </div>

      <!-- Cache Status Alert -->
      <div v-if="dataFromSession" class="alert alert-info d-flex align-items-center mb-3">
        <font-awesome-icon :icon="['fas','database']" class="me-2" />
        <strong>Data dari Cache (30 menit)</strong> - Hanya load page yang belum di-cache
      </div>

      <!-- Anomaly Alert -->
      <div v-if="anomalyCount > 0" class="alert alert-warning d-flex align-items-center mb-3">
        <font-awesome-icon :icon="['fas','exclamation-triangle']" class="me-3" size="2x" />
        <div class="flex-grow-1">
          <strong>Deteksi Anomali!</strong><br>
          Ditemukan <strong>{{ anomalyCount }}</strong> data dengan anomali pengukuran.
          <button class="btn btn-sm btn-warning ms-3" @click="showAnomaliesOnly = !showAnomaliesOnly">
            {{ showAnomaliesOnly ? 'Tampilkan Semua' : 'Tampilkan Anomali Saja' }}
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="card border-0 rounded shadow mb-3">
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-xl-4">
              <label class="form-label mb-1">Cari (NIK / Nama Anak / Desa / Posyandu)</label>
              <input v-model="q" type="text" class="form-control" placeholder="Ketik minimal 2 karakter…" />
            </div>

            <div class="col-xl-4">
              <label class="form-label mb-1 d-block">Tanggal Ukur (Dari–Sampai)</label>
              <div class="input-group">
                <input v-model="tglFrom" type="date" class="form-control" />
                <span class="input-group-text">s.d.</span>
                <input v-model="tglTo" type="date" class="form-control" />
              </div>
            </div>

            <div class="col-xl-4">
              <label class="form-label mb-1">Desa & Posyandu</label>
              <select v-model="filterPosyandu" class="form-select" :class="{ 'text-placeholder': !filterPosyandu }">
                <option value="">— Semua Desa & Posyandu —</option>
                <option v-for="opt in posyanduOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>

            <!-- Numeric ranges -->
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

            <!-- Additional filters -->
            <div class="col-12">
              <div class="row g-3 align-items-end">
                <div class="col-xl-5">
                  <label class="form-label mb-1 d-block">ASI 0–6 Bulan (pilih satu/lebih)</label>
                  <div class="d-flex flex-wrap gap-2">
                    <label v-for="m in [0,1,2,3,4,5,6]" :key="m" class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" :value="m" v-model="filterAsiMonths" />
                      <span class="form-check-label">Bulan {{ m }}</span>
                    </label>
                  </div>
                </div>

                <div class="col-xl-3">
                  <label class="form-label mb-1">Vitamin A</label>
                  <select v-model="filterVita" class="form-select">
                    <option value="">— Semua —</option>
                    <option value="BIRU">Biru</option>
                    <option value="MERAH">Merah</option>
                    <option value="KOSONG">Kosong</option>
                  </select>
                </div>

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

            <div class="col-xl-4 ms-auto">
              <button class="btn btn-danger w-100" @click="resetFilter; resetCache()">Reset Semua Filter</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="card border-0 rounded shadow">
        <div class="card-body">
          <div v-if="loading" class="alert alert-info">Memuat…</div>
          <div v-else-if="errorMsg" class="alert alert-danger">{{ errorMsg }}</div>

          <template v-else>
            <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
              <span>Total data: {{ totalItems }} | Halaman: {{ currentPage }} / {{ totalPages }}</span>
              <span>Ditampilkan: {{ filteredRows.length }}</span>
              <span v-if="anomalyCount > 0" class="text-warning">
                <font-awesome-icon :icon="['fas','exclamation-triangle']" class="me-1" />
                Anomali: {{ anomalyCount }}
              </span>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-striped align-middle">
                <thead class="bg-dark text-white">
                  <tr>
                    <th style="width: 60px">Status</th>
                    <th>NIK</th>
                    <th>Nama Anak</th>
                    <th>Desa & Posyandu</th>
                    <th>Tanggal Ukur</th>
                    <th>Berat (kg)</th>
                    <th>Tinggi (cm)</th>
                    <th>LILA (cm)</th>
                    <th>LK (cm)</th>
                    <th>Cara Ukur</th>
                    <th>ASI 0–6</th>
                    <th>Vit A</th>
                    <th>Kelas Ibu</th>
                    <th style="width: 22%">Actions</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-if="loading" class="text-center">
                    <td colspan="14">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>

                  <tr v-if="filteredRows.length === 0">
                    <td colspan="14" class="text-center">
                      <div class="alert alert-warning mb-0">Data tidak ditemukan.</div>
                    </td>
                  </tr>

                  <tr v-for="(item, index) in filteredRows" :key="item?.id ?? index" 
                      :class="{ 'table-warning': item.anomalyInfo?.hasAnomaly }">
                    <td class="text-center">
                      <template v-if="item.anomalyInfo?.hasAnomaly">
                        <button 
                          class="btn btn-sm btn-warning p-1" 
                          @click="showDataComparison(item)"
                          :title="item.anomalyInfo.type.map(t => getAnomalyLabel(t)).join(', ')"
                        >
                          <font-awesome-icon :icon="['fas','exclamation-triangle']" />
                        </button>
                      </template>
                      <span v-else class="text-muted">{{ index + 1 }}</span>
                    </td>

                    <td>{{ item?.anak?.nik ?? '-' }}</td>
                    <td>{{ item?.anak?.nama_anak ?? '-' }}</td>
                    <td>{{ posyanduLabel(item) || '-' }}</td>
                    <td>{{ toDisplayDate(item?.tanggal_ukur) }}</td>
                    <td>{{ safe(item?.berat) || '-' }}</td>
                    <td>{{ safe(item?.tinggi) || '-' }}</td>
                    <td>{{ safe(item?.lila) || '-' }}</td>
                    <td>{{ safe(item?.lingkar_kepala) || '-' }}</td>
                    <td>{{ item?.cara_ukur || '-' }}</td>

                    <td>
                      <template v-if="asiTagList(item).length">
                        <span v-for="bulan in asiTagList(item)" :key="bulan" class="badge bg-success me-1 mb-1">
                          <font-awesome-icon :icon="['fas','check']" class="me-1" />
                          Bulan {{ bulan }}
                        </span>
                      </template>
                      <span v-else class="text-muted">—</span>
                    </td>

                    <td>{{ getVita(item) || '-' }}</td>

                    <td>
                      <span class="badge" :class="fmtBool(getKelasIbu(item)) ? 'bg-success' : 'bg-secondary'">
                        {{ fmtBool(getKelasIbu(item)) ? 'Ya' : 'Tidak' }}
                      </span>
                    </td>

                    <td class="text-center">
                      <div class="d-flex justify-content-start flex-wrap gap-2">
                        <button 
                          v-if="item.anomalyInfo?.hasAnomaly"
                          @click.prevent="showDataComparison(item)"
                          class="btn btn-sm btn-warning rounded-sm shadow border-0 me-2"
                        >
                          COMPARE
                        </button>
                      
                        <button 
                          @click.prevent="viewRiwayat(item?.anak?.nik, item?.anak?.nama_anak)"
                          class="btn btn-sm btn-secondary rounded-sm shadow border-0 me-2"
                        >
                          RIWAYAT
                        </button>

                        <button 
                          @click.prevent="deleteData(item?.id)"
                          class="btn btn-sm btn-danger rounded-sm shadow border-0 me-2"
                        >
                          DELETE
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-4">
              <ul class="pagination justify-content-center">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                  <button class="page-link" @click="goToPage(1)" :disabled="currentPage === 1">Pertama</button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                  <button class="page-link" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1">Sebelumnya</button>
                </li>

                <li v-for="page in Math.min(totalPages, 5)" :key="page" class="page-item" :class="{ active: currentPage === page }">
                  <button class="page-link" @click="goToPage(page)">{{ page }}</button>
                </li>

                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                  <button class="page-link" @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages">Berikutnya</button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                  <button class="page-link" @click="goToPage(totalPages)" :disabled="currentPage === totalPages">Terakhir</button>
                </li>
              </ul>
            </nav>
          </template>
        </div>
      </div>
    </div></div>
  </div>
</template>

<style>
.swal2-actions-gap { gap: .5rem; }
.text-placeholder { color: #6c757d; }
.table td .badge { font-weight: 600; }
.table-riwayat th,
.table-riwayat td {
  white-space: nowrap;
  font-size: 0.9rem;
}
.table-riwayat td .badge {
  font-weight: 600;
}
.table-warning {
  background-color: #fff3cd !important;
}
</style>