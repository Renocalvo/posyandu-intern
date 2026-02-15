<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { logout } from './utils/auth'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()

async function doLogout() {
  const result = await Swal.fire({
    title: 'Konfirmasi Logout',
    text: 'Apakah Anda yakin ingin keluar?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Logout',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    await logout()
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil keluar',
      timer: 1200,
      showConfirmButton: false
    })
    router.replace({ name: 'login' })
  }
}

const showNav = computed(() => route.name !== 'login')
</script>

<template>
  <div>
    <nav v-if="showNav" class="navbar navbar-expand-lg bg-dark" data-bs-theme="dark">
      <div class="container">
        <router-link :to="{ name: 'home' }" class="navbar-brand">HOME</router-link>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item d-flex gap-2">
              <router-link :to="{ name: 'users.index' }" class="nav-link">USERS</router-link>
            </li>
            <li class="nav-item d-flex gap-2">
              <router-link :to="{ name: 'posyandu.index' }" class="nav-link">POSYANDU</router-link>
            </li>
            <li class="nav-item d-flex gap-2">
              <router-link :to="{ name: 'anak.index' }" class="nav-link">ANAK</router-link>
            </li>
            <li class="nav-item d-flex gap-2">
              <router-link :to="{ name: 'anak-pengukuran.index' }" class="nav-link">ANAK PENGUKURAN</router-link>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <button class="btn btn-outline-danger" @click="doLogout">Logout</button>
          </ul>
        </div>
      </div>
    </nav>

    <!-- konten halaman -->
    <router-view />
  </div>
</template>
