import { createRouter, createWebHistory } from 'vue-router'
import Login from '../views/Login.vue'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },

    // Home
    { path: '/', name: 'home', component: () => import('../views/home.vue'), meta: { requiresAuth: true } },

    // Users
    { path: '/users', name: 'users.index', component: () => import('../views/users/index.vue'), meta: { requiresAuth: true } },
    { path: '/users/create', name: 'users.create', component: () => import('../views/users/create.vue'), meta: { requiresAuth: true } },
    { path: '/users/:id/edit', name: 'users.edit', component: () => import('../views/users/edit.vue'), meta: { requiresAuth: true } },

    // Posyandu
    { path: '/posyandu', name: 'posyandu.index', component: () => import('../views/posyandu/index.vue'), meta: { requiresAuth: true } },
    { path: '/posyandu/create', name: 'posyandu.create', component: () => import('../views/posyandu/create.vue'), meta: { requiresAuth: true } },
    { path: '/posyandu/:id/edit', name: 'posyandu.edit', component: () => import('../views/posyandu/edit.vue'), meta: { requiresAuth: true } },

    // Anak
    { path: '/anak', name: 'anak.index', component: () => import('../views/anak/index.vue'), meta: { requiresAuth: true } },
    { path: '/anak/create', name: 'anak.create', component: () => import('../views/anak/create.vue'), meta: { requiresAuth: true } },
    { path: '/anak/:id/edit', name: 'anak.edit', component: () => import('../views/anak/edit.vue'), meta: { requiresAuth: true } },

    // Anak Pengukuran
    { path: '/anak-pengukuran', name: 'anak-pengukuran.index', component: () => import('../views/anak-pengukuran/index.vue'), meta: { requiresAuth: true } },
    { path: '/anak-pengukuran/create', name: 'anak-pengukuran.create', component: () => import('../views/anak-pengukuran/create.vue'), meta: { requiresAuth: true } },
    // { path: '/anak-pengukuran/:nik/edit', name: 'anak-pengukuran.edit', component: () => import('../views/anak-pengukuran/edit.vue'), meta: { requiresAuth: true } },

    // Import
    { path: '/anak/import', name: 'anak.import', component: () => import('../views/anak/import.vue'), meta: { requiresAuth: true } },
    { path: '/anak/export', name: 'anak.export', component: () => import('../views/anak/export.vue'), meta: { requiresAuth: true } },
    { path: '/anak-pengukuran/import', name: 'anak-pengukuran.import', component: () => import('../views/anak-pengukuran/import.vue'), meta: { requiresAuth: true } },
    { path: '/anak-pengukuran/export', name: 'anak-pengukuran.export', component: () => import('../views/anak-pengukuran/export.vue'), meta: { requiresAuth: true } },

    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
      meta: { requiresAuth: true }
    }
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem('access_token')

  if (to.meta.requiresAuth && !token) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && token) {
    return { name: 'home' }
  }
})

export default router
