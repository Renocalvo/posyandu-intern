<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { posyanduLabelFromRow as posyanduLabel } from '../../utils/labels'
import api from '../../api'
import Swal from 'sweetalert2'

/* =================== STATE =================== */
const apk = ref([])
const loading = ref(false)
const errorMsg = ref('')

/* =================== FILTERS =================== */
const q = ref('')                 // keyword: NIK / Nama / Label posyandu
const filterPosyandu = ref('')    // label util "Desa X - Posy. Y"
const tglFrom = ref('')           // yyyy-mm-dd
const tglTo = ref('')             // yyyy-mm-dd
const beratFrom = ref(''); const beratTo = ref('')
const tinggiFrom = ref(''); const tinggiTo = ref('')
const lilaFrom = ref(''); const lilaTo = ref('')
const lkFrom = ref(''); const lkTo = ref('')

// Tambahan (selaras dengan halaman Export):
const filterAsiMonths = ref([])   // array 0..6
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

/* =================== NORMALISASI =================== */
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

/* =================== HELPERS (tanggal stabil, anti H-1) =================== */
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

  // D/M/Y atau D-M-Y
  let m = s.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})$/)
  if (m) { let yy = +m[3]; if (yy < 100) yy += 2000; return `${yy}-${String(+m[2]).padStart(2,'0')}-${String(+m[1]).padStart(2,'0')}` }

  // Y/M/D
  m = s.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/)
  if (m) return `${m[1]}-${String(+m[2]).padStart(2,'0')}-${String(+m[3]).padStart(2,'0')}`

  // Ada jam/timezone -> ambil tanggal lokal
  if (/[T ]\d{2}:\d{2}/.test(s) || /Z|[+\-]\d{2}:?\d{2}$/.test(s)) {
    const d = new Date(s); if (!isNaN(d.getTime())) return _ymdFromLocalDate(d)
  }

  // Fallback
  const d2 = new Date(s.replace(' ', 'T'))
  if (!isNaN(d2.getTime())) return _ymdFromLocalDate(d2)
  return s
}
const toDisplayDate = (v) => {
  const iso = fmtISO(v)
  if (!iso) return '-'
  const [y,m,d] = iso.split('-')
  return `${d}/${m}/${y}` // tampil "DD/MM/YYYY"
}
function inDateRange(value, from, to) {
  const ymd = fmtISO(value); if (!ymd) return false
  const f = from ? fmtISO(from) : null
  const t = to   ? fmtISO(to)   : null
  if (f && ymd < f) return false
  if (t && ymd > t) return false
  return true
}

/* =================== HELPERS LAIN =================== */
const safe = (v) => (v ?? v === 0 ? v : '')
const fmtBool = (v) => v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true'

const parseNum = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : null }
const inNumRange = (val, from, to) => {
  const n = parseNum(val); if (n === null) return false
  const f = parseNum(from); const t = parseNum(to)
  if (f !== null && n < f) return false
  if (t !== null && n > t) return false
  return true
}

/* === ASI 0..6 → list bulan yang true === */
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
    if (on) out.push(i + 1) // tampil "Bulan 1..7"
  }
  return out
}

/* === Vitamin A (warna) === */
function getVita(item) {
  // normalisasi beberapa kemungkinan nama field
  return item?.vita ?? item?.vit_a ?? item?.vita_a ?? ''
}
const vitaColor = (v) => {
  const s = String(v ?? '').trim().toLowerCase()
  if (s === 'biru') return 'BIRU'
  if (s === 'merah') return 'MERAH'
  return '' // bukan warna
}

/* === Kelas Ibu === */
const getKelasIbu = (item) => item?.kelas_ibu_balita ?? item?.kelasIbuBalita

/* =================== FILTERING =================== */
const filteredRows = computed(() => {
  const keyword = q.value.trim().toLowerCase()
  const selAsi   = filterAsiMonths.value
  const selVita  = filterVita.value
  const selKelas = filterKelasIbu.value

  return rows.value.filter(item => {
    const label = posyanduLabel(item) || ''

    // Posyandu
    if (filterPosyandu.value && label !== filterPosyandu.value) return false

    // Tanggal Ukur
    if ((tglFrom.value || tglTo.value) && !inDateRange(item?.tanggal_ukur, tglFrom.value, tglTo.value)) return false

    // Rentang numerik
    if ((beratFrom.value || beratTo.value)   && !inNumRange(item?.berat,           beratFrom.value,  beratTo.value))   return false
    if ((tinggiFrom.value || tinggiTo.value) && !inNumRange(item?.tinggi,          tinggiFrom.value, tinggiTo.value))  return false
    if ((lilaFrom.value || lilaTo.value)     && !inNumRange(item?.lila,            lilaFrom.value,   lilaTo.value))    return false
    if ((lkFrom.value || lkTo.value)         && !inNumRange(item?.lingkar_kepala,  lkFrom.value,     lkTo.value))      return false

    // === Filter tambahan ===

    // ASI: minimal salah satu bulan pilihan bernilai true
    if (Array.isArray(selAsi) && selAsi.length > 0) {
      const anyOn = selAsi.some(mIdx => {
        const raw = getAsi(item, mIdx)
        return raw === true || raw === 1 || raw === '1' || String(raw).toLowerCase?.() === 'true'
      })
      if (!anyOn) return false
    }

    // Vitamin A: warna spesifik atau kosong
    if (selVita) {
      const color = vitaColor(getVita(item))
      if (selVita === 'KOSONG') {
        const isTruthy = fmtBool(getVita(item))
        if (isTruthy || color !== '') return false
      } else {
        if (color !== selVita) return false
      }
    }

    // Kelas Ibu
    if (selKelas === 'YA'    && !fmtBool(getKelasIbu(item))) return false
    if (selKelas === 'TIDAK' &&  fmtBool(getKelasIbu(item))) return false

    // Keyword
    if (!keyword) return true
    const nikStr   = String(item?.anak?.nik ?? '').toLowerCase()
    const namaStr  = String(item?.anak?.nama_anak ?? '').toLowerCase()
    const labelStr = String(label).toLowerCase()
    return nikStr.includes(keyword) || namaStr.includes(keyword) || labelStr.includes(keyword)
  })
})

/* =================== ACTIONS =================== */
const deleteData = async (id) => {
  const { isConfirmed } = await Swal.fire({
    title: 'Hapus Data ini?',
    text: 'Tindakan ini tidak bisa dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary', actions: 'swal2-actions-gap' },
    buttonsStyling: true,
  })
  if (!isConfirmed) return
  try {
    await api.delete(`/anak-pengukuran/${id}`)
    await Swal.fire({ title: 'Terhapus', text: 'Data berhasil dihapus.', icon: 'success', timer: 1400, showConfirmButton: false })
    fetchData()
  } catch (e) {
    await Swal.fire({ title: 'Gagal', text: e?.response?.data?.message ?? 'Gagal menghapus data.', icon: 'error' })
  }
}

const viewRiwayat = async (nik, nama = '') => {
  Swal.fire({
    title: 'Riwayat Pengukuran',
    html: `<div class="mb-2"><b>${nama || '-'}</b></div><div class="small text-muted">NIK: <code>${nik || '-'}</code></div><div class="mt-2">Memuat data riwayat…</div>`,
    allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
    didOpen: () => Swal.showLoading(), width: '1000px',
    customClass: { popup: 'swal-riwayat', actions: 'swal2-actions-gap' }, buttonsStyling: false,
  })

  // helper kecil untuk tag ASI lama di log
  const asiBadges = (r) => {
    const flags = []
    for (let i = 0; i <= 6; i++) {
      const k = `asi_bulan_${i}_lama`
      const v = r?.[k]
      const on = v === true || v === 1 || v === '1' || String(v).toLowerCase?.() === 'true'
      if (on) flags.push(i) // tampil "Bulan 0..6" sesuai naming log
    }
    if (!flags.length) return '—'
    return flags.map(b => `<span class="badge bg-success me-1 mb-1">Bulan ${b}</span>`).join(' ')
  }

  // label posyandu lama (jika relasi tersedia di response)
  const posLabel = (r) => {
    const desa = r?.posyandu?.desa || r?.desa_lama || ''
    const nama = r?.posyandu?.nama || r?.posyandu_lama || ''
    if (!desa && !nama) return '—'
    if (desa && nama) return `${desa} - Posy. ${nama}`
    return desa || nama
  }

  const safeBool = (v) => (v === true || v === 1 || v === '1' || String(v).toLowerCase?.() === 'true')

  try {
    const res = await api.get(`/log-pengukuran/nik/${nik}`, { headers: { Accept: 'application/json' } })
    const list = Array.isArray(res.data) ? res.data
      : Array.isArray(res.data?.data) ? res.data.data
      : Array.isArray(res.data?.data?.data) ? res.data.data.data
      : []

    // urutkan terbaru dulu
    list.sort((a, b) => {
      const ax = new Date(a.diubah_pada || a.updated_at || a.tanggal_ukur_lama || a.tanggal_ukur || 0).getTime()
      const bx = new Date(b.diubah_pada || b.updated_at || b.tanggal_ukur_lama || b.tanggal_ukur || 0).getTime()
      return bx - ax
    })

    const headerInfo = `
      <div class="mb-3">
        <div class="mb-1"><span class="badge bg-secondary me-2">Nama</span><b>${nama || '-'}</b></div>
        <div><span class="badge bg-secondary me-2">NIK</span><code>${nik || '-'}</code></div>
      </div>`

    const rowsHtml = (list || []).map((r, i) => {
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
          <td><span class="badge ${safeBool(r.kelas_ibu_balita_lama) ? 'bg-success' : 'bg-secondary'}">
                ${safeBool(r.kelas_ibu_balita_lama) ? 'Ya' : 'Tidak'}
              </span></td>
          <td>${waktuUbah}</td>
        </tr>`
    }).join('')

    const html = `${headerInfo}
      <div style="max-height:70vh; overflow:auto;">
        <div class="mb-2 small text-muted">Menampilkan ${list.length} entri riwayat.</div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-striped align-middle table-riwayat">
            <thead class="table-dark">
              <tr>
                <th style="min-width:40px;">No</th>
                <th style="min-width:110px;">Tanggal Ukur</th>
                <th style="min-width:180px;">Posyandu (lama)</th>
                <th style="min-width:80px;">Berat</th>
                <th style="min-width:80px;">Tinggi</th>
                <th style="min-width:80px;">LILA</th>
                <th style="min-width:100px;">Lingkar Kepala</th>
                <th style="min-width:100px;">Cara Ukur</th>
                <th style="min-width:100px;">Vitamin A</th>
                <th style="min-width:160px;">ASI 0–6 (lama)</th>
                <th style="min-width:120px;">Kelas Ibu (lama)</th>
                <th style="min-width:150px;">Diubah pada</th>
              </tr>
            </thead>
            <tbody>${rowsHtml || '<tr><td colspan="12" class="text-center text-muted">Tidak ada riwayat.</td></tr>'}</tbody>
          </table>
        </div>
      </div>`

    Swal.hideLoading()
    Swal.update({
      title: 'Riwayat Pengukuran',
      html,
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
             <div class="small text-muted">NIK: <code>${nik || '-'}</code></div>
             <div class="text-danger mt-2">${e?.response?.data?.message ?? 'Tidak dapat mengambil riwayat pengukuran.'}</div>`,
      showCloseButton: true, showConfirmButton: true, confirmButtonText: 'Tutup',
      allowEscapeKey: true, width: '900px',
      customClass: { popup: 'swal-riwayat', actions: 'swal2-actions-gap', confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    })
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
  filterAsiMonths.value = []
  filterVita.value = ''
  filterKelasIbu.value = ''
}
</script>

<template>
  <div class="container mt-5 mb-5">
    <div class="row"><div class="col-md-12">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <RouterLink :to="{ name: 'anak-pengukuran.create' }" class="btn btn-md btn-success rounded shadow border-0">+ Tambah Data</RouterLink>
        <div class="d-flex align-items-center flex-wrap gap-2">
          <RouterLink :to="{ name: 'anak-pengukuran.import' }" class="btn btn-md btn-primary rounded shadow border-0 d-inline-flex align-items-center">
            <font-awesome-icon :icon="['fas','file-import']" class="me-2" />Import File
          </RouterLink>
          <RouterLink :to="{ name: 'anak-pengukuran.export' }" class="btn btn-md btn-warning rounded d-inline-flex align-items-center">
            <font-awesome-icon :icon="['fas','file-export']" class="me-2" />Export Data
          </RouterLink>
        </div>
      </div>

      <!-- ========== FILTERS (selaras dengan halaman Export) ========== -->
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
                      <input class="form-check-input" type="checkbox" :value="m" v-model="filterAsiMonths" />
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
                  <div class="form-text">Nilai warna (Februari/Agustus). “Kosong” = tidak terisi warna/boolean.</div>
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

            <!-- Tombol Reset -->
            <div class="col-xl-4 ms-auto">
              <div class="row g-2 justify-content-end">
                <div class="col-12">
                  <button class="btn btn-danger w-100" @click="resetFilter">Reset Semua Filter</button>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- TABEL -->
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
                    <th>ASI 0–6 Bulan</th>
                    <th>Vitamin A</th>
                    <th>Kelas Ibu Balita</th>
                    <th style="width: 22%">Actions</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-if="filteredRows.length === 0">
                    <td colspan="14" class="text-center">
                      <div class="alert alert-warning mb-0">Data tidak ditemukan.</div>
                    </td>
                  </tr>

                  <tr v-for="(item, index) in filteredRows" :key="item?.anak?.nik ?? index">
                    <td>{{ index + 1 }}</td>
                    <td>{{ item?.anak?.nik ?? '-/(BELUM ADA)' }}</td>
                    <td>{{ item?.anak?.nama_anak ?? '-' }}</td>
                    <td>{{ posyanduLabel(item) || '-' }}</td>
                    <td>{{ toDisplayDate(item?.tanggal_ukur) }}</td>
                    <td>{{ safe(item?.berat) || '-' }} kg</td>
                    <td>{{ safe(item?.tinggi) || '-' }} cm</td>
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
                      <button @click.prevent="viewRiwayat(item?.anak?.nik, item?.anak?.nama_anak)"
                        class="btn btn-sm btn-secondary rounded-sm shadow border-0 me-2">RIWAYAT</button>
                      <button @click.prevent="deleteData(item?.anak?.nik)"
                        class="btn btn-sm btn-danger rounded-sm shadow border-0">DELETE</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
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

</style>
