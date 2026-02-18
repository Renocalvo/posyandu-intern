<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import Swal from 'sweetalert2'

const router = useRouter()

/* ===================== STATE FORM ===================== */
const nik = ref('')
const nikSearch = ref('')
const tanggal_ukur = ref('')
const berat = ref('')
const tinggi = ref('')
const lila = ref('')
const lingkar_kepala = ref('')
const cara_ukur = ref('')
const vit_a = ref('')
const asi = reactive({ 0:false, 1:false, 2:false, 3:false, 4:false, 5:false, 6:false })
const kelas_ibu_balita = ref(false)

const setAsiAll = (val) => { 
  for (let i=0; i<=6; i++) asi[i] = !!val 
}

/* ===================== TYPEAHEAD ===================== */
const suggestions = ref([])
const loadingSuggest = ref(false)
const listOpen = ref(false)
let searchTimer = null
let lastQueryId = 0

function normalizeResourceList(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

function posyanduTextFromAnak(a) {
  const desa = (a?.posyandu?.desa ?? a?.desa_posyandu ?? a?.desa ?? '').toUpperCase()
  const nama = (a?.posyandu?.nama ?? a?.nama_posyandu ?? a?.posyandu_nama ?? '')
  if (desa && nama) return `Desa ${desa}-Posy. ${nama}`
  if (desa) return `Desa ${desa}`
  if (nama) return `Posy. ${nama}`
  return '-'
}

const anakProfile = ref(null)
const anakId = ref(null)
const loadingAnak = ref(false)

const posyanduIdFromProfile = computed(() => {
  const a = anakProfile.value || {}
  const id = a.posyandu_id ?? a?.posyandu?.id ?? null
  return (id === '' || id === undefined) ? null : id
})

async function fetchSuggestions(q) {
  const queryId = ++lastQueryId
  loadingSuggest.value = true
  try {
    const res = await api.get('/anak', { 
      params: { search: q, page: 1 }, 
      headers: { Accept: 'application/json' } 
    })
    if (queryId !== lastQueryId) return
    
    suggestions.value = normalizeResourceList(res.data).map(a => ({
      nik: String(a?.nik || '').replace(/\D/g, ''),
      nama: a?.nama_anak || '-',
      posText: posyanduTextFromAnak(a)
    }))
  } catch {
    if (queryId !== lastQueryId) return
    suggestions.value = []
  } finally {
    if (queryId === lastQueryId) loadingSuggest.value = false
  }
}

const filteredSuggestions = computed(() => {
  const raw = suggestions.value || []
  const qRaw = (nikSearch.value || '').trim()
  const qDigits = qRaw.replace(/\D/g, '')
  const qLower = qRaw.toLowerCase()

  if (qDigits.length >= 2 && /^\d+$/.test(qRaw)) {
    return raw.filter(o => o.nik.startsWith(qDigits)).slice(0, 20)
  }
  if (qLower.length >= 2) {
    return raw.filter(o => {
      const nama = (o.nama || '').toLowerCase()
      const pos = (o.posText || '').toLowerCase()
      const matchNama = nama.startsWith(qLower) || nama.split(/\s+/).some(w => w.startsWith(qLower))
      const matchPos = pos.startsWith(qLower) || pos.split(/\s+/).some(w => w.startsWith(qLower))
      return matchNama || matchPos
    }).slice(0, 20)
  }
  return []
})

function onSearchInput() {
  listOpen.value = true
  clearTimeout(searchTimer)
  const s = (nikSearch.value || '').trim()
  const digits = s.replace(/\D/g, '')

  if (digits.length >= 13 && digits.length <= 16 && /^\d+$/.test(s)) {
    nik.value = digits
    suggestions.value = []
    fetchAnakByNik(digits)
    return
  }

  if (s.length < 2) {
    suggestions.value = []
    return
  }
  searchTimer = setTimeout(() => fetchSuggestions(s), 300)
}

function chooseSuggestion(opt) {
  nik.value = opt.nik
  nikSearch.value = opt.nik
  listOpen.value = false
  suggestions.value = []
  fetchAnakByNik(opt.nik)
}

function closeListLater() { 
  setTimeout(() => (listOpen.value = false), 120) 
}

async function fetchAnakByNik(n) {
  if (!n || n.length < 13 || n.length > 16) {
    anakProfile.value = null
    anakId.value = null
    return
  }
  loadingAnak.value = true
  try {
    const res = await api.get(`/anak/nik/${n}`, { 
      headers: { Accept: 'application/json' } 
    })
    anakProfile.value = res?.data?.data ?? res?.data ?? null
    anakId.value = anakProfile.value?.id ?? null
  } catch {
    anakProfile.value = null
    anakId.value = null
  } finally {
    loadingAnak.value = false
  }
}

/* ===================== USIA & VITAMIN ===================== */
function diffMonths(tglLahir, tanggalUkur) {
  if (!tglLahir || !tanggalUkur) return null
  const a = new Date(tglLahir)
  const b = new Date(tanggalUkur)
  if (isNaN(a.getTime()) || isNaN(b.getTime())) return null
  let months = (b.getFullYear() - a.getFullYear()) * 12 + (b.getMonth() - a.getMonth())
  if (b.getDate() < a.getDate()) months -= 1
  return Math.max(months, 0)
}

const usiaBulan = computed(() => {
  const lahir = anakProfile.value?.tgl_lahir
  return diffMonths(lahir, tanggal_ukur.value)
})

const bulanPengukuran = computed(() => {
  if (!tanggal_ukur.value) return null
  return new Date(tanggal_ukur.value).getMonth() + 1
})

const isBulanVitamin = computed(() => 
  bulanPengukuran.value === 2 || bulanPengukuran.value === 8
)

const eligibleBiru = computed(() => {
  const m = usiaBulan.value
  return isBulanVitamin.value && m !== null && m >= 6 && m <= 11
})

const eligibleMerah = computed(() => {
  const m = usiaBulan.value
  return isBulanVitamin.value && m !== null && m >= 12 && m <= 60
})

// Auto-suggest cara ukur
const recommendedCaraUkur = computed(() => {
  const m = usiaBulan.value
  if (m === null) return null
  return m < 24 ? 'Terlentang' : 'Berdiri'
})

// Watch untuk auto-set cara_ukur jika belum diisi
watch(recommendedCaraUkur, (newVal) => {
  if (newVal && !cara_ukur.value) {
    cara_ukur.value = newVal
  }
})

/* ===================== VALIDASI & SUBMIT ===================== */
const errors = ref({})
const generalError = ref('')
const saving = ref(false)

const canSubmit = computed(() =>
  !saving.value &&
  nik.value.trim().length >= 13 && 
  nik.value.trim().length <= 16 &&
  !!tanggal_ukur.value &&
  String(berat.value).length > 0 &&
  String(tinggi.value).length > 0 &&
  (cara_ukur.value === 'Terlentang' || cara_ukur.value === 'Berdiri')
)

async function storeData() {
  errors.value = {}
  generalError.value = ''

  // Validasi client-side
  if (nik.value.trim().length < 13 || nik.value.trim().length > 16) {
    errors.value.nik = ['NIK harus antara 13 sampai 16 digit.']
  }
  if (!tanggal_ukur.value) {
    errors.value.tanggal_ukur = ['Tanggal ukur wajib diisi.']
  }
  if (String(berat.value).length === 0) {
    errors.value.berat = ['Berat wajib diisi.']
  }
  if (String(tinggi.value).length === 0) {
    errors.value.tinggi = ['Tinggi wajib diisi.']
  }
  if (!(cara_ukur.value === 'Terlentang' || cara_ukur.value === 'Berdiri')) {
    errors.value.cara_ukur = ['Cara ukur wajib dipilih.']
  }
  
  if (Object.keys(errors.value).length) return

  if (!anakProfile.value || !anakId.value) {
    errors.value.nik = ['NIK belum terdaftar. Pilih dari daftar atau tambahkan Anak terlebih dahulu.']
    return
  }

  saving.value = true
  
  try {
    const payload = {
      anak_id: anakId.value,
      posyandu_id: posyanduIdFromProfile.value,
      tanggal_ukur: tanggal_ukur.value,
      berat: berat.value || null,
      tinggi: tinggi.value || null,
      lila: lila.value || null,
      lingkar_kepala: lingkar_kepala.value || null,
      cara_ukur: cara_ukur.value || null,
      vit_a: vit_a.value || null,
      asi_bulan_0: !!asi[0],
      asi_bulan_1: !!asi[1],
      asi_bulan_2: !!asi[2],
      asi_bulan_3: !!asi[3],
      asi_bulan_4: !!asi[4],
      asi_bulan_5: !!asi[5],
      asi_bulan_6: !!asi[6],
      kelas_ibu_balita: !!kelas_ibu_balita.value,
    }

    // POST ke /anak-pengukuran
    // Controller akan otomatis cek: jika ada data lama -> update + log, jika tidak -> create
    const response = await api.post('/anak-pengukuran', payload, { 
      headers: { Accept: 'application/json' } 
    })

    const isUpdate = response?.data?.message?.includes('diperbarui')
    
    await Swal.fire({ 
      icon: 'success', 
      title: 'Berhasil', 
      text: isUpdate 
        ? 'Data diperbarui & log tersimpan.' 
        : 'Data berhasil ditambahkan.', 
      timer: 1500, 
      showConfirmButton: false 
    })
    
    router.push({ name: 'anak-pengukuran.index' })
    
  } catch (err) {
    const data = err?.response?.data
    errors.value = data?.errors ?? data ?? {}
    generalError.value = data?.message ?? 'Gagal menyimpan data.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <RouterLink
          :to="{ name: 'anak-pengukuran.index' }"
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
            <h5 class="mb-3">Tambah Pengukuran Anak</h5>

            <form @submit.prevent="storeData">
              <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>

              <div class="row g-3">
                <!-- NIK Search -->
                <div class="col-md-4 position-relative">
                  <label class="form-label fw-bold">
                    NIK / Nama Anak <span class="text-danger">*</span>
                  </label>
                  <input
                    class="form-control"
                    v-model="nikSearch"
                    name="nikSearch"
                    placeholder="Ketik NIK atau Nama Anak"
                    @input="onSearchInput"
                    @focus="listOpen = (nikSearch?.length || 0) >= 2"
                    @blur="closeListLater"
                    autocomplete="off"
                    maxlength="16"
                  />
                  
                  <!-- Dropdown suggestions -->
                  <div
                    v-if="listOpen && (nikSearch?.length || 0) >= 2"
                    class="list-group position-absolute w-100 shadow-sm"
                    style="z-index:1000; max-height: 280px; overflow:auto;"
                  >
                    <button 
                      v-if="loadingSuggest" 
                      type="button" 
                      class="list-group-item list-group-item-action disabled"
                    >
                      Mencari…
                    </button>
                    <template v-else>
                      <button
                        v-for="opt in filteredSuggestions"
                        :key="opt.nik"
                        type="button"
                        class="list-group-item list-group-item-action"
                        @mousedown.prevent="chooseSuggestion(opt)"
                      >
                        <div class="d-flex justify-content-between">
                          <span><b>{{ opt.nik }}</b> — {{ opt.nama }}</span>
                          <small class="text-muted">{{ opt.posText }}</small>
                        </div>
                      </button>
                      <div 
                        v-if="!filteredSuggestions.length" 
                        class="list-group-item text-muted"
                      >
                        Tidak ada hasil.
                      </div>
                    </template>
                  </div>
                  
                  <div class="form-text">
                    Ketik minimal 2 karakter untuk mencari, atau ketik NIK lengkap (13-16 digit)
                  </div>
                  <div v-if="errors?.nik?.length" class="alert alert-danger mt-2">
                    {{ errors.nik[0] }}
                  </div>
                </div>

                <!-- Profile Display -->
                <div class="col-md-8">
                  <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-bold mb-2">Identitas Anak</div>
                    <div v-if="anakProfile" class="row g-2 small">
                      <div class="col-md-6"><b>NIK:</b> {{ nik }}</div>
                      <div class="col-md-6"><b>Nama:</b> {{ anakProfile?.nama_anak || '-' }}</div>
                      <div class="col-md-6"><b>JK:</b> {{ anakProfile?.jenis_kelamin || '-' }}</div>
                      <div class="col-md-6"><b>Tgl Lahir:</b> {{ anakProfile?.tgl_lahir || '-' }}</div>
                      <div class="col-md-12">
                        <b>Posyandu:</b> {{ posyanduTextFromAnak(anakProfile) }}
                      </div>
                    </div>
                    <div v-else class="text-muted small">
                      Pilih anak dari daftar atau ketik NIK lengkap
                    </div>
                  </div>
                </div>
              </div>

              <!-- Data Pengukuran -->
              <div class="row g-3 mt-1">
                <div class="col-md-4">
                  <label class="form-label fw-bold">
                    Tanggal Ukur <span class="text-danger">*</span>
                  </label>
                  <input 
                    type="date" 
                    class="form-control" 
                    v-model="tanggal_ukur" 
                  />
                  <div v-if="errors?.tanggal_ukur?.length" class="alert alert-danger mt-2">
                    {{ errors.tanggal_ukur[0] }}
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">
                    Berat (kg) <span class="text-danger">*</span>
                  </label>
                  <input 
                    type="number" 
                    step="0.01" 
                    class="form-control" 
                    v-model="berat" 
                  />
                  <div v-if="errors?.berat?.length" class="alert alert-danger mt-2">
                    {{ errors.berat[0] }}
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">
                    Tinggi (cm) <span class="text-danger">*</span>
                  </label>
                  <input 
                    type="number" 
                    step="0.1" 
                    class="form-control" 
                    v-model="tinggi" 
                  />
                  <div v-if="errors?.tinggi?.length" class="alert alert-danger mt-2">
                    {{ errors.tinggi[0] }}
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">LILA (cm)</label>
                  <input 
                    type="number" 
                    step="0.1" 
                    class="form-control" 
                    v-model="lila" 
                  />
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Lingkar Kepala (cm)</label>
                  <input 
                    type="number" 
                    step="0.1" 
                    class="form-control" 
                    v-model="lingkar_kepala" 
                  />
                </div>

                <!-- Cara Ukur -->
                <div class="col-md-4">
                  <label class="form-label fw-bold d-block">
                    Cara Ukur <span class="text-danger">*</span>
                  </label>
                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="radio"
                      id="ukur-terlentang"
                      value="Terlentang"
                      v-model="cara_ukur"
                    >
                    <label class="form-check-label" for="ukur-terlentang">
                      Terlentang
                    </label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="radio"
                      id="ukur-berdiri"
                      value="Berdiri"
                      v-model="cara_ukur"
                    >
                    <label class="form-check-label" for="ukur-berdiri">
                      Berdiri
                    </label>
                  </div>
                  <div class="small mt-2">
                    <span class="text-muted">
                      Rekomendasi: <b>{{ recommendedCaraUkur || '-' }}</b>
                    </span>
                  </div>
                  <div v-if="errors?.cara_ukur?.length" class="alert alert-danger mt-2">
                    {{ errors.cara_ukur[0] }}
                  </div>
                </div>

                <!-- Vitamin A -->
                <div class="col-md-8">
                  <label class="form-label fw-bold d-block">
                    Vitamin A (Februari & Agustus saja)
                  </label>
                  <div class="d-flex align-items-center flex-wrap gap-3">
                    <div class="form-check form-check-inline">
                      <input
                        class="form-check-input"
                        type="radio"
                        id="vit-biru"
                        value="Biru"
                        v-model="vit_a"
                        :disabled="!eligibleBiru"
                      >
                      <label class="form-check-label" for="vit-biru">
                        <span class="badge rounded-pill bg-primary">Biru</span>
                        <small class="text-muted ms-1">(6–11 bln)</small>
                      </label>
                    </div>

                    <div class="form-check form-check-inline">
                      <input
                        class="form-check-input"
                        type="radio"
                        id="vit-merah"
                        value="Merah"
                        v-model="vit_a"
                        :disabled="!eligibleMerah"
                      >
                      <label class="form-check-label" for="vit-merah">
                        <span class="badge rounded-pill bg-danger">Merah</span>
                        <small class="text-muted ms-1">(12–60 bln)</small>
                      </label>
                    </div>

                    <button 
                      type="button" 
                      class="btn btn-sm btn-outline-secondary" 
                      @click="vit_a = ''" 
                      :disabled="!isBulanVitamin"
                    >
                      Kosongkan
                    </button>
                  </div>

                  <div class="form-text mt-1">
                    <template v-if="!tanggal_ukur">
                      Set tanggal ukur untuk mengecek kelayakan bulan.
                    </template>
                    <template v-else-if="!isBulanVitamin">
                      Bulan pengukuran bukan Februari/Agustus — pilihan vitamin dinonaktifkan.
                    </template>
                    <template v-else>
                      Usia: <b>{{ usiaBulan ?? '-' }}</b> bln — 
                      <span :class="eligibleBiru ? 'text-success' : 'text-muted'">
                        Biru (6–11)
                      </span>,
                      <span :class="eligibleMerah ? 'text-success' : 'text-muted'">
                        Merah (12–60)
                      </span>.
                    </template>
                  </div>
                </div>

                <!-- ASI -->
                <div class="col-md-12">
                  <label class="form-label fw-bold d-block">
                    ASI Eksklusif (0–6 bulan)
                  </label>
                  <div class="d-flex flex-wrap gap-2">
                    <label 
                      v-for="b in 7" 
                      :key="b" 
                      class="btn btn-sm btn-outline-primary"
                    >
                      <input 
                        class="form-check-input me-1" 
                        type="checkbox" 
                        :checked="asi[b-1]" 
                        @change="asi[b-1] = $event.target.checked"
                      >
                      Bulan {{ b-1 }}
                    </label>
                    <div class="ms-2 d-inline-flex gap-2">
                      <button 
                        type="button" 
                        class="btn btn-sm btn-success" 
                        @click="setAsiAll(true)"
                      >
                        Semua Ya
                      </button>
                      <button 
                        type="button" 
                        class="btn btn-sm btn-danger" 
                        @click="setAsiAll(false)"
                      >
                        Kosongkan
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Kelas Ibu Balita -->
                <div class="col-md-4">
                  <div class="mt-3 form-check form-switch">
                    <input 
                      class="form-check-input" 
                      type="checkbox" 
                      id="kelas-ibu" 
                      v-model="kelas_ibu_balita"
                    >
                    <label class="form-check-label fw-bold" for="kelas-ibu">
                      Kelas Ibu Balita
                    </label>
                  </div>
                </div>
              </div>

              <!-- Submit Button -->
              <div class="mt-4 d-flex align-items-center flex-wrap gap-2">
                <button 
                  class="btn btn-primary" 
                  :disabled="!canSubmit"
                >
                  {{ saving ? 'Menyimpan…' : 'Simpan Data' }}
                </button>
                <small class="text-muted" v-if="!canSubmit">
                  Lengkapi field yang wajib (*)
                </small>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.list-group { 
  max-width: 100%; 
}
</style>