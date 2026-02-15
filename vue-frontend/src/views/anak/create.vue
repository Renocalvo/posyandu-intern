<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'

const router = useRouter()

// ===== STATE FORM =====
const nik = ref('')
const anak_ke = ref('')
const tgl_lahir = ref('')
const jenis_kelamin = ref('')
const nomor_KK = ref('')
const nama_anak = ref('')
const usia_hamil = ref('')
const berat_lahir = ref('')
const panjang_lahir = ref('')
const lingkar_kepala_lahir = ref('')
const kia = ref(false)
const kia_bayi_kecil = ref(false)
const imd = ref(false)
const nama_ortu = ref('')
const nik_ortu = ref('')
const hp_ortu = ref('')
const posyandu_id = ref('')
const rt = ref('')
const rw = ref('')

// ===== POSYANDU OPTIONS =====
const posyandus = ref([])
const loadingPosyandu = ref(false)

const posyanduLabel = (p) => {
  const desa = (p?.desa ?? '').toUpperCase()
  const nm = p?.nama ?? ''
  if (desa && nm) return `Desa ${desa}-Posy. ${nm}`
  if (desa) return `Desa ${desa}`
  if (nm) return `Posy. ${nm}`
  return `ID: ${p?.id ?? '-'}`
}

const fetchPosyandu = async () => {
  loadingPosyandu.value = true
  try {
    const res = await api.get('/posyandu', { headers: { Accept: 'application/json' } })
    const data = res.data
    posyandus.value = Array.isArray(data)
      ? data
      : Array.isArray(data?.data) ? data.data
      : Array.isArray(data?.data?.data) ? data.data.data
      : []
  } catch {
    posyandus.value = []
  } finally {
    loadingPosyandu.value = false
  }
}

onMounted(fetchPosyandu)

// ===== ERROR/LOADING =====
const errors = ref({})
const generalError = ref('')
const loading = ref(false)

// ===== VALIDASI RINGAN =====
const canSubmit = computed(() =>
  !loading &&
  nik.value.trim().length >= 13 && nik.value.trim().length <= 16 &&
  nama_anak.value.trim().length > 0 &&
  tgl_lahir.value &&
  (jenis_kelamin.value === 'L' || jenis_kelamin.value === 'P')
  // posyandu_id → nullable di rules kamu. Kalau mau wajib, tambahkan di sini & ubah rule jadi required.
)

// ===== SUBMIT =====
const storeAnak = async () => {
  // guard UI (tampilkan error cepat kalau form belum lengkap)
  errors.value = {}
  if ((nik.value.trim().length < 13 || nik.value.trim().length > 16)) errors.value.nik = ['NIK harus antara 13 sampai 16 digit.'];
  if (!nama_anak.value.trim()) errors.value.nama_anak = ['Nama anak wajib diisi.']
  if (!tgl_lahir.value) errors.value.tgl_lahir = ['Tanggal lahir wajib diisi.']
  if (!(jenis_kelamin.value === 'L' || jenis_kelamin.value === 'P')) {
    errors.value.jenis_kelamin = ['Jenis kelamin wajib dipilih.']
  }
  if (Object.keys(errors.value).length) return

  loading.value = true
  generalError.value = ''

  try {
    const payload = {
      nik: nik.value.trim(),
      anak_ke: anak_ke.value || null,
      tgl_lahir: tgl_lahir.value,
      jenis_kelamin: jenis_kelamin.value,
      nomor_KK: nomor_KK.value,
      nama_anak: nama_anak.value.trim(),
      usia_hamil: usia_hamil.value || null,
      berat_lahir: berat_lahir.value || null,
      panjang_lahir: panjang_lahir.value || null,
      lingkar_kepala_lahir: lingkar_kepala_lahir.value || null,
      kia: !!kia.value,
      kia_bayi_kecil: !!kia_bayi_kecil.value,
      imd: !!imd.value,
      nama_ortu: nama_ortu.value || null,
      nik_ortu: nik_ortu.value || null,
      hp_ortu: hp_ortu.value || null,
      posyandu_id: posyandu_id.value || null,
      rt: rt.value || null,
      rw: rw.value || null,
    }

    await api.post('/anak', payload, { headers: { Accept: 'application/json' } })
    router.push({ name: 'anak.index' })
  } catch (err) {
    const data = err?.response?.data
    errors.value = data?.errors ?? data ?? {}
    generalError.value = data?.message ?? 'Gagal menyimpan data.'
    console.log('Create failed:', data)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container mt-5">
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
            <h5 class="mb-3">Tambah Anak</h5>

            <form @submit.prevent="storeAnak">
              <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">NIK</label>
                  <input class="form-control" v-model="nik" name="nik" placeholder="13-16 digit" maxlength="16" minLength="13"/>
                  <div v-if="errors?.nik?.length" class="alert alert-danger mt-2">{{ errors.nik[0] }}</div>
                </div>

                <div class="col-md-3">
                  <label class="form-label fw-bold">Anak Ke</label>
                  <input type="number" min="1" class="form-control" v-model="anak_ke" name="anak_ke" />
                  <div v-if="errors?.anak_ke?.length" class="alert alert-danger mt-2">{{ errors.anak_ke[0] }}</div>
                </div>

                <div class="col-md-3">
                  <label class="form-label fw-bold">Tgl Lahir</label>
                  <input type="date" class="form-control" v-model="tgl_lahir" name="tgl_lahir" />
                  <div v-if="errors?.tgl_lahir?.length" class="alert alert-danger mt-2">{{ errors.tgl_lahir[0] }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Jenis Kelamin</label>
                  <select class="form-select" v-model="jenis_kelamin" name="jenis_kelamin">
                    <option value="">-- Pilih --</option>
                    <option value="L">Laki-Laki</option>
                    <option value="P">Perempuan</option>
                  </select>
                  <div v-if="errors?.jenis_kelamin?.length" class="alert alert-danger mt-2">{{ errors.jenis_kelamin[0] }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Nama Anak</label>
                  <input class="form-control" v-model="nama_anak" name="nama_anak" placeholder="Nama Anak" />
                  <div v-if="errors?.nama_anak?.length" class="alert alert-danger mt-2">{{ errors.nama_anak[0] }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Nomor KK</label>
                  <input class="form-control" v-model="nomor_KK" name="nomor_KK" />
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-4">
                  <label class="form-label fw-bold">Usia Hamil (minggu)</label>
                  <input class="form-control" v-model="usia_hamil" name="usia_hamil" />
                  <div v-if="errors?.usia_hamil?.length" class="alert alert-danger mt-2">{{ errors.usia_hamil[0] }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Berat Lahir (gram)</label>
                  <input class="form-control" v-model="berat_lahir" name="berat_lahir" />
                  <div v-if="errors?.berat_lahir?.length" class="alert alert-danger mt-2">{{ errors.berat_lahir[0] }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Panjang Lahir (cm)</label>
                  <input class="form-control" v-model="panjang_lahir" name="panjang_lahir" />
                  <div v-if="errors?.panjang_lahir?.length" class="alert alert-danger mt-2">{{ errors.panjang_lahir[0] }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Lingkar Kepala Lahir (cm)</label>
                  <input class="form-control" v-model="lingkar_kepala_lahir" name="lingkar_kepala_lahir" />
                  <div v-if="errors?.lingkar_kepala_lahir?.length" class="alert alert-danger mt-2">{{ errors.lingkar_kepala_lahir[0] }}</div>
                </div>

                <div class="col-md-8 d-flex align-items-end flex-wrap gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="kia" v-model="kia" />
                    <label class="form-check-label" for="kia">KIA</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="kia_bayi_kecil" v-model="kia_bayi_kecil" />
                    <label class="form-check-label" for="kia_bayi_kecil">KIA Bayi Kecil</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="imd" v-model="imd" />
                    <label class="form-check-label" for="imd">IMD</label>
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-4">
                  <label class="form-label fw-bold">Nama Ortu</label>
                  <input class="form-control" v-model="nama_ortu" name="nama_ortu" />
                  <div v-if="errors?.nama_ortu?.length" class="alert alert-danger mt-2">{{ errors.nama_ortu[0] }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-bold">NIK Ortu</label>
                  <input class="form-control" v-model="nik_ortu" name="nik_ortu" />
                  <div v-if="errors?.nik_ortu?.length" class="alert alert-danger mt-2">{{ errors.nik_ortu[0] }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-bold">HP Ortu</label>
                  <input class="form-control" v-model="hp_ortu" name="hp_ortu" />
                  <div v-if="errors?.hp_ortu?.length" class="alert alert-danger mt-2">{{ errors.hp_ortu[0] }}</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">Posyandu (opsional)</label>
                  <select class="form-select" v-model="posyandu_id" name="posyandu_id">
                    <option value="">-- Pilih Posyandu --</option>
                    <option v-for="p in posyandus" :key="p.id" :value="p.id">
                      {{ posyanduLabel(p) }}
                    </option>
                  </select>
                  <div class="form-text" v-if="loadingPosyandu">Memuat daftar posyandu…</div>
                  <div v-if="errors?.posyandu_id?.length" class="alert alert-danger mt-2">{{ errors.posyandu_id[0] }}</div>
                </div>

                <div class="col-md-3">
                  <label class="form-label fw-bold">RT</label>
                  <input class="form-control" v-model="rt" name="rt" />
                  <div v-if="errors?.rt?.length" class="alert alert-danger mt-2">{{ errors.rt[0] }}</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-bold">RW</label>
                  <input class="form-control" v-model="rw" name="rw" />
                  <div v-if="errors?.rw?.length" class="alert alert-danger mt-2">{{ errors.rw[0] }}</div>
                </div>
              </div>

              <div class="mt-4">
                <button class="btn btn-primary" :disabled="loading">
                  {{ loading ? 'Saving…' : 'Save' }}
                </button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>
