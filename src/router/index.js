import { createRouter, createWebHistory } from 'vue-router'
import { isLoggedIn } from '@/stores/authStore'

/* ── Lazy-loaded page components ── */
const HomePage       = () => import('@/pages/HomePage.vue')
const AboutPage      = () => import('@/pages/AboutPage.vue')
const ContactPage    = () => import('@/pages/contactpage.vue')
const LoginPage      = () => import('@/pages/LoginPage.vue')
const RegisterPage   = () => import('@/pages/RegisterPage.vue')
const ProjectsPage   = () => import('@/pages/ProjectsPage.vue')
const BoardPage      = () => import('@/pages/BoardPage.vue')
const DashboardPage  = () => import('@/pages/DashboardPage.vue')
const CalendarPage   = () => import('@/pages/CalendarPage.vue')
const PublicBoardPage = () => import('@/pages/PublicBoardPage.vue')

const routes = [
  /* ── Public ── */
  { path: '/',         name: 'home',     component: HomePage },
  { path: '/about',    name: 'about',    component: AboutPage },
  { path: '/contact',  name: 'contact',  component: ContactPage },
  { path: '/login',    name: 'login',    component: LoginPage },
  { path: '/register', name: 'register', component: RegisterPage },
  /* public read-only project share */
  { path: '/p/:shareId', name: 'public-board', component: PublicBoardPage },

  /* ── Authenticated (wrapped in AppLayout) ── */
  {
    path: '/projects',
    name: 'projects',
    component: ProjectsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/projects/:id',
    name: 'board',
    component: BoardPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: CalendarPage,
    meta: { requiresAuth: true },
  },

  /* ── Fallback ── */
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

/* ── Navigation guard ── */
router.beforeEach((to) => {
  if (to.meta.requiresAuth && !isLoggedIn.value) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  // Redirect logged-in users away from auth pages
  if ((to.name === 'login' || to.name === 'register') && isLoggedIn.value) {
    return { name: 'dashboard' }
  }
})

export default router
