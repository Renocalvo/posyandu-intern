<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'

const router = useRouter()

// state
const desa = ref('')
const nama = ref('')

const errors = ref({})
const generalError = ref('')
const loading = ref(false)

// validasi ringan
const canSubmit = computed(() =>
  !loading &&
  desa.value.trim().length > 0 &&
  nama.value.trim().length > 0
)

// submit
const storeUser = async () => {
  loading.value = true
  generalError.value = ''
  errors.value = {}

  try {
    const formData = new FormData()
    formData.append('desa', desa.value)
    formData.append('nama', nama.value)

    await api.post('/posyandu', formData, {
      headers: { Accept: 'application/json' },
    })

    router.push({ path: '/posyandu' })
  } catch (err) {
    const data = err?.response?.data
    // Validator::errors() bisa langsung object { field: [msg] }
    errors.value = data?.errors ?? data ?? {}
    generalError.value = data?.message ?? 'Gagal menyimpan data.'
    console.log('Save failed:', data)
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
          :to="{ name: 'posyandu.index' }"
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
            <form @submit.prevent="storeUser">
              <div v-if="generalError" class="alert alert-danger">{{ generalError }}</div>

              <div class="mb-3">
                <label class="form-label fw-bold">Desa</label>
                <select class="form-select" v-model="desa">
                  <option value="">-- Pilih Desa --</option>
                  <option value="Oro-oro Ombo">Oro-oro Ombo</option>
                  <option value="Ngaglik">Ngaglik</option>
                  <option value="Pesanggrahan">Pesanggrahan</option>
                  <option value="Songgokerto">Songgokerto</option>
                  <option value="Sumberejo">Sumberejo</option>
                </select>
                <div v-if="errors?.desa?.length" class="alert alert-danger mt-2">
                  {{ errors.desa[0] }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-bold">Nama Posyandu</label>
                <input class="form-control" v-model="nama" placeholder="Nama Posyandu" />
                <div v-if="errors?.nama?.length" class="alert alert-danger mt-2">
                  {{ errors.nama[0] }}
                </div>
              </div>

              <button class="btn btn-primary" :disabled="loading">
                {{ loading ? 'Saving…' : 'Save' }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
