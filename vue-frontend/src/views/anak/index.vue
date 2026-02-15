<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { posyanduLabelFromRow as posyanduLabel } from '../../utils/labels'
import api from '../../api'
import Swal from 'sweetalert2'

const anak = ref([])
const loading = ref(false)
const errorMsg = ref('')

// 🔎 state search & filters
const q = ref('')                 // search bebas: NIK / nama anak / posyandu / nama ortu
const filterPosyandu = ref('')    // label posyandu (exact)
const filterJK = ref('')          // '', 'L', 'P'
const filterKIA = ref('')         // '', 'yes', 'no'
const filterKiaBayiKecil = ref('')// '', 'yes', 'no'
const filterIMD = ref('')         // '', 'yes', 'no'
const filterRT = ref('')          // string/angka
const filterRW = ref('')          // string/angka

// Rentang tanggal lahir (berdekatan)
const filterTglFrom = ref('')     // yyyy-mm-dd
const filterTglTo = ref('')       // yyyy-mm-dd

// Rentang numerik
const beratFrom = ref('')         // kg
const beratTo = ref('')
const panjangFrom = ref('')       // cm (tinggi/panjang lahir)
const panjangTo = ref('')
const lingkarFrom = ref('')       // cm
const lingkarTo = ref('')

// Normalisasi rows dari berbagai bentuk respons
const rows = computed(() => {
  const u = anak.value
  if (Array.isArray(u)) return u
  if (Array.isArray(u?.data)) return u.data
  if (Array.isArray(u?.data?.data)) return u.data.data
  return []
})

// Opsi dropdown Posyandu (unik & terurut)
const posyanduOptions = computed(() => {
  const labels = rows.value.map(r => posyanduLabel(r)).filter(Boolean)
  return Array.from(new Set(labels)).sort((a, b) => a.localeCompare(b, 'id'))
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

// Helpers tampilan
const toYesNo = (v) =>
  v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true' ? 'Ya' : 'Tidak'

const fmtDate = (v) => (v ? new Date(v).toLocaleDateString('id-ID') : '-')
const safe = (v) => (v ?? v === 0 ? v : '-')

const genderLabel = (jk) => {
  if (!jk) return '-'
  return jk.toUpperCase() === 'P' ? 'Perempuan' : jk.toUpperCase() === 'L' ? 'Laki-Laki' : jk
}

// Utils filter
const normalizeBool = (v) =>
  (v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true')

const matchTriBool = (value, choice) => {
  if (!choice) return true // semua
  const b = normalizeBool(value)
  return choice === 'yes' ? b === true : b === false
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
    t.setHours(23, 59, 59, 999) // inklusif
    if (d > t) return false
  }
  return true
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

// 🧮 filter utama
const filteredRows = computed(() => {
  const keyword = q.value.trim().toLowerCase()
  const posy = filterPosyandu.value
  const jk = (filterJK.value || '').toUpperCase()
  const rt = filterRT.value.toString().trim()
  const rw = filterRW.value.toString().trim()
  const from = filterTglFrom.value
  const to = filterTglTo.value

  return rows.value.filter(item => {
    // 1) Dropdown Posyandu
    if (posy && posyanduLabel(item) !== posy) return false

    // 2) Jenis Kelamin
    if (jk) {
      const itemJK = String(item?.jenis_kelamin ?? '').toUpperCase()
      if (itemJK !== jk) return false
    }

    // 3) KIA / KIA Bayi Kecil / IMD
    if (!matchTriBool(item?.kia, filterKIA.value)) return false
    if (!matchTriBool(item?.kia_bayi_kecil, filterKiaBayiKecil.value)) return false
    if (!matchTriBool(item?.imd, filterIMD.value)) return false

    // 4) RT / RW (exact)
    if (rt) {
      const v = String(item?.rt ?? '').trim()
      if (v !== rt) return false
    }
    if (rw) {
      const v = String(item?.rw ?? '').trim()
      if (v !== rw) return false
    }

    // 5) Rentang tanggal lahir (opsional)
    if ((from || to) && !inDateRange(item?.tgl_lahir, from, to)) return false

    // 6) Rentang numerik
    if ((beratFrom.value || beratTo.value) &&
        !inNumRange(item?.berat_lahir, beratFrom.value, beratTo.value)) return false

    if ((panjangFrom.value || panjangTo.value) &&
        !inNumRange(item?.panjang_lahir, panjangFrom.value, panjangTo.value)) return false

    if ((lingkarFrom.value || lingkarTo.value) &&
        !inNumRange(item?.lingkar_kepala_lahir, lingkarFrom.value, lingkarTo.value)) return false

    // 7) Search bebas (OR ke 4 field)
    if (!keyword) return true
    const nikStr   = String(item?.nik ?? '').toLowerCase()
    const namaStr  = String(item?.nama_anak ?? '').toLowerCase()
    const ortuStr  = String(item?.nama_ortu ?? '').toLowerCase()

    return (
      nikStr.includes(keyword) ||
      namaStr.includes(keyword) ||
      ortuStr.includes(keyword)
    )
  })
})

// Fetch data
const fetchDataAnak = async () => {
  loading.value = true
  errorMsg.value = ''
  await api
    .get('/anak')
    .then((response) => {
      anak.value = response.data ?? []
    })
    .catch(() => {
      anak.value = []
      errorMsg.value = 'Gagal memuat data.'
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(fetchDataAnak)

// Reset semua filter
const resetFilter = () => {
  q.value = ''
  filterPosyandu.value = ''
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
}

// Hapus dengan SweetAlert2
const deleteAnak = async (id) => {
  const { isConfirmed } = await Swal.fire({
    title: 'Hapus anak ini?',
    text: 'Tindakan ini tidak bisa dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    customClass: {
      confirmButton: 'btn btn-danger',
      cancelButton: 'btn btn-secondary',
      actions: 'swal2-actions-gap',
    },
    buttonsStyling: false,
  })

  if (!isConfirmed) return

  try {
    await api.delete(`/anak/${id}`)
    await Swal.fire({
      title: 'Terhapus',
      text: 'Anak berhasil dihapus.',
      icon: 'success',
      timer: 1400,
      showConfirmButton: false,
    })
    fetchDataAnak()
  } catch (e) {
    await Swal.fire({
      title: 'Gagal',
      text: e?.response?.data?.message ?? 'Gagal menghapus data.',
      icon: 'error',
    })
  }
}
</script>

<template>
  <div class="container mt-5 mb-5">
    <div class="row">
      <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <RouterLink
            :to="{ name: 'anak.create' }"
            class="btn btn-md btn-success rounded shadow border-0"
          >
            + Tambah Anak
          </RouterLink>

          <div class="d-flex align-items-center flex-wrap gap-2">
            <RouterLink
              :to="{ name: 'anak.import' }"
              class="btn btn-md btn-primary rounded shadow border-0 d-inline-flex align-items-center"
            >
              <font-awesome-icon :icon="['fas','file-import']" class="me-2" />
              Import File
            </RouterLink>

            <RouterLink
              :to="{ name: 'anak.export' }"
              class="btn btn-md btn-warning rounded d-inline-flex align-items-center"
            >
              <font-awesome-icon :icon="['fas','file-export']" class="me-2" />
              Export Data
            </RouterLink>
          </div>
        </div>

        <!-- 🔎 Toolbar Search & Filters -->
        <div class="card border-0 rounded shadow mb-3">
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <!-- Search -->
              <div class="col-lg-4">
                <label class="form-label mb-1">Cari (NIK / Nama Anak   / Nama Ortu)</label>
                <input
                  v-model="q"
                  type="text"
                  class="form-control"
                  placeholder="Ketik minimal 2 karakter…"
                />
              </div>

              <!-- Tanggal Lahir (kiri) -->
              <div class="col-lg-4">
                <label class="form-label mb-1 d-block">Tanggal Lahir (Dari–Sampai)</label>
                <div class="input-group">
                  <input v-model="filterTglFrom" type="date" class="form-control" />
                  <span class="input-group-text">s.d.</span>
                  <input v-model="filterTglTo" type="date" class="form-control" />
                </div>
              </div>

              <!-- Posyandu -->
              <div class="col-lg-4">
                <label class="form-label mb-1">Filter Posyandu</label>
                <select v-model="filterPosyandu" class="form-select">
                  <option value="" class="placeholder-option">— Semua Posyandu —</option>
                  <option v-for="opt in posyanduOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>

              <!-- Jenis Kelamin -->
              <div class="col-lg-2">
                <label class="form-label mb-1">Jenis Kelamin</label>
                <select v-model="filterJK" class="form-select">
                  <option v-for="o in jkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>

              <!-- KIA -->
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

              <!-- Rentang numerik -->
              <div class="col-12">
                <div class="row g-3">
                  <!-- Berat lahir -->
                  <div class="col-md-4">
                    <label class="form-label mb-1 d-block">Berat Lahir (kg)</label>
                    <div class="input-group">
                      <input v-model="beratFrom" type="number" step="0.01" min="0" class="form-control" placeholder="min" />
                      <span class="input-group-text">s.d.</span>
                      <input v-model="beratTo" type="number" step="0.01" min="0" class="form-control" placeholder="max" />
                    </div>
                  </div>

                  <!-- Panjang/Tinggi lahir -->
                  <div class="col-md-4">
                    <label class="form-label mb-1 d-block">Tinggi/Panjang Lahir (cm)</label>
                    <div class="input-group">
                      <input v-model="panjangFrom" type="number" step="0.1" min="0" class="form-control" placeholder="min" />
                      <span class="input-group-text">s.d.</span>
                      <input v-model="panjangTo" type="number" step="0.1" min="0" class="form-control" placeholder="max" />
                    </div>
                  </div>

                  <!-- Lingkar kepala -->
                  <div class="col-md-4">
                    <label class="form-label mb-1 d-block">Lingkar Kepala Lahir (cm)</label>
                    <div class="input-group">
                      <input v-model="lingkarFrom" type="number" step="0.1" min="0" class="form-control" placeholder="min" />
                      <span class="input-group-text">s.d.</span>
                      <input v-model="lingkarTo" type="number" step="0.1" min="0" class="form-control" placeholder="max" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Reset -->
              <div class="col-lg-2 ms-auto d-grid">
                <button class="btn btn-danger" @click="resetFilter">Reset</button>
              </div>
            </div>
          </div>
        </div>


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
                      <th scope="col">RT</th>
                      <th scope="col">RW</th>
                      <th scope="col" style="width: 12%">Actions</th>
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
                      <td>{{ safe(item?.rt) }}</td>
                      <td>{{ safe(item?.rw) }}</td>

                      <td class="text-center">
                        <RouterLink
                          :to="{ name: 'anak.edit', params: { id: item?.id ?? item?.nik } }"
                          class="btn btn-sm btn-primary rounded-sm shadow border-0 me-2"
                        >
                          EDIT
                        </RouterLink>
                        <button
                          @click.prevent="deleteAnak(item?.id ?? item?.nik)"
                          class="btn btn-sm btn-danger rounded-sm shadow border-0"
                        >
                          DELETE
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style>
/* Jarak antar tombol konfirmasi SweetAlert2 */
.swal2-actions-gap { gap: 0.5rem; }

</style>
