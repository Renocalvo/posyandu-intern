<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../api'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()

const form = ref({ username: '', password: '' })
const loading = ref(false)
const showPass = ref(false)
const errMsg = ref('')

async function onSubmit() {
  errMsg.value = ''
  if (!form.value.username || !form.value.password) {
    errMsg.value = 'Username dan password wajib diisi.'
    return
  }

  loading.value = true
  try {
    const { data } = await api.post('/login', {
      username: form.value.username,
      password: form.value.password
    })

    if (!data?.success) throw new Error(data?.message || 'Login gagal')

    // simpan token & user
    localStorage.setItem('access_token', data.access_token)
    localStorage.setItem('user', JSON.stringify(data.user))

    // set header default axios juga (immediate)
    api.defaults.headers.Authorization = `Bearer ${data.access_token}`

    await Swal.fire({ icon:'success', title:'Login berhasil', timer:900, showConfirmButton:false })
    // redirect ke halaman sebelumnya (jika ada) atau dashboard
    const redirect = route.query.redirect || { name:'home' }
    router.replace(redirect)
  } catch (e) {
    errMsg.value = e?.response?.data?.message || e?.message || 'Username atau password salah'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container py-5" style="max-width:420px">
    <h4 class="mb-3">Login Page</h4>

    <div v-if="errMsg" class="alert alert-danger py-2">{{ errMsg }}</div>

    <form @submit.prevent="onSubmit" class="card shadow-sm border-0">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input
            v-model.trim="form.username"
            type="text"
            class="form-control"
            placeholder="Username"
            autocomplete="username"
          />
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <input
              v-model.trim="form.password"
              :type="showPass ? 'text' : 'password'"
              class="form-control"
              placeholder="Password"
              autocomplete="current-password"
            />
            <span class="input-group-text" style="cursor:pointer" @click="showPass=!showPass">
              <font-awesome-icon :icon="showPass ? 'fa-eye-slash' : 'fa-eye'" />
            </span>
          </div>
        </div>

        <button class="btn btn-primary w-100" :disabled="loading">
          {{ loading ? 'Memproses…' : 'Login' }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.container { min-height: 70vh; }
</style>
