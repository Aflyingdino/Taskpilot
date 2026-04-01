<script setup>
import PublicNav from '@/components/PublicNav.vue'

const features = [
  {
    title: 'Kanban Boards',
    desc: 'Organize tasks into groups with drag-and-drop between columns.',
    icon: 'board',
  },
  {
    title: 'Labels & Priorities',
    desc: 'Tag tasks with custom color labels and four priority levels.',
    icon: 'label',
  },
  {
    title: 'Deadlines & Calendar',
    desc: 'Assign due dates and view all tasks in a monthly calendar.',
    icon: 'calendar',
  },
  {
    title: 'Comments',
    desc: 'Discuss tasks with your team directly in the task detail view.',
    icon: 'comment',
  },
  {
    title: 'Public Share Links',
    desc: 'Share a read-only view of any board with stakeholders via a link.',
    icon: 'share',
  },
  {
    title: 'Team Roles',
    desc: 'Invite members as Owner, Admin, or User with scoped permissions.',
    icon: 'team',
  },
]

const steps = [
  {
    n: '01',
    title: 'Create a project',
    desc: 'Set up a project, define your board groups, and invite your team with the appropriate roles.',
  },
  {
    n: '02',
    title: 'Build your board',
    desc: 'Add tasks, assign labels, set priorities, attach deadlines, and move cards between groups as work progresses.',
  },
  {
    n: '03',
    title: 'Track and share',
    desc: 'Monitor deadlines in the calendar view and generate a read-only public link for anyone who needs visibility without an account.',
  },
]
</script>

<template>
  <div class="home">
    <PublicNav />

    <!-- Hero -->
    <section class="hero">
      <h1 class="hero__title">Manage projects<br>without the noise</h1>
      <p class="hero__subtitle">
        TaskPilot gives your team kanban boards, deadlines, labels, and comments in one focused workspace.
      </p>
      <div class="hero__cta">
        <router-link to="/register" class="btn btn-primary btn-lg">Get started free</router-link>
        <router-link to="/about" class="btn btn-ghost btn-lg">Learn more</router-link>
      </div>
    </section>

    <!-- Features -->
    <section class="features">
      <div class="features__inner">
        <div class="section-label">Features</div>
        <h2 class="section-title">Everything your team needs</h2>
        <div class="features__grid">
          <div class="feature-card" v-for="f in features" :key="f.title">
            <div class="feature-icon">
              <svg v-if="f.icon === 'board'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="5" height="14" rx="1.5"/><rect x="10" y="3" width="5" height="10" rx="1.5"/><rect x="17" y="3" width="5" height="17" rx="1.5"/></svg>
              <svg v-else-if="f.icon === 'label'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
              <svg v-else-if="f.icon === 'calendar'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
              <svg v-else-if="f.icon === 'comment'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              <svg v-else-if="f.icon === 'share'" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
              <svg v-else width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="feature-body">
              <h3>{{ f.title }}</h3>
              <p>{{ f.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section class="how">
      <div class="how__inner">
        <div class="section-label">How it works</div>
        <h2 class="section-title">Up and running in minutes</h2>
        <div class="how__steps">
          <div class="step" v-for="s in steps" :key="s.n">
            <span class="step__n">{{ s.n }}</span>
            <div class="step__body">
              <h3>{{ s.title }}</h3>
              <p>{{ s.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="home-footer">
      <p>2026 TaskPilot &nbsp;&middot;&nbsp; <router-link to="/about">About</router-link> &nbsp;&middot;&nbsp; <router-link to="/contact">Contact</router-link></p>
    </footer>
  </div>
</template>



<style scoped>
.home {
  min-height: 100vh;
  background: var(--color-surface-0);
  color: var(--color-text-1);
  display: flex;
  flex-direction: column;
}

/* Shared */
.section-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--color-accent);
  margin-bottom: 10px;
}
.section-title {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--color-text-1);
  margin-bottom: 32px;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.btn-primary {
  background: var(--color-accent);
  color: #fff;
  border-color: var(--color-accent);
}
.btn-primary:hover { background: var(--color-accent-hover); border-color: var(--color-accent-hover); }
.btn-ghost {
  background: transparent;
  color: var(--color-text-2);
  border-color: var(--color-border);
}
.btn-ghost:hover { background: var(--color-surface-2); color: var(--color-text-1); }
.btn-lg { padding: 11px 24px; font-size: 14px; border-radius: 8px; }

/* Hero */
.hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 108px 48px 96px;
  gap: 20px;
  border-bottom: 1px solid var(--color-border-sub);
}
.hero__title {
  font-size: clamp(32px, 4.5vw, 58px);
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: var(--color-text-1);
  max-width: 620px;
}
.hero__subtitle {
  font-size: 17px;
  color: var(--color-text-2);
  max-width: 480px;
  line-height: 1.75;
}
.hero__cta {
  display: flex;
  gap: 10px;
  margin-top: 12px;
  flex-wrap: wrap;
  justify-content: center;
}

/* Features */
.features {
  border-bottom: 1px solid var(--color-border-sub);
  padding: 72px 48px;
}
.features__inner {
  max-width: 1000px;
  margin: 0 auto;
}
.features__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
  background: var(--color-border);
}
.feature-card {
  background: var(--color-surface-1);
  padding: 22px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  transition: background 0.15s;
}
.feature-card:hover { background: var(--color-surface-2); }
.feature-icon {
  flex-shrink: 0;
  width: 34px;
  height: 34px;
  border-radius: 7px;
  background: var(--color-surface-3);
  border: 1px solid var(--color-border);
  color: var(--color-accent);
  display: flex;
  align-items: center;
  justify-content: center;
}
.feature-body h3 { font-size: 13px; font-weight: 600; color: var(--color-text-1); margin-bottom: 4px; }
.feature-body p { font-size: 12px; color: var(--color-text-2); line-height: 1.65; }

/* How it works */
.how {
  border-bottom: 1px solid var(--color-border-sub);
  padding: 72px 48px;
}
.how__inner {
  max-width: 1000px;
  margin: 0 auto;
}
.how__steps {
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
}
.step {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  padding: 26px 28px;
  background: var(--color-surface-1);
  border-bottom: 1px solid var(--color-border);
}
.step:last-child { border-bottom: none; }
.step__n {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--color-accent);
  min-width: 24px;
  margin-top: 2px;
  font-variant-numeric: tabular-nums;
}
.step__body h3 { font-size: 14px; font-weight: 600; color: var(--color-text-1); margin-bottom: 4px; }
.step__body p { font-size: 13px; color: var(--color-text-2); line-height: 1.65; }

/* Footer */
.home-footer {
  padding: 28px 48px;
  text-align: center;
  font-size: 12px;
  color: var(--color-text-3);
  margin-top: auto;
}
.home-footer a { color: var(--color-text-2); text-decoration: none; }
.home-footer a:hover { color: var(--color-text-1); }
</style>
