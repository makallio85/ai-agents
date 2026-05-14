<script setup>
import { ref, computed } from 'vue';

const count = ref(0);
const stamp = computed(() => new Date().toISOString());
</script>

<template>
    <section class="hello-coolify">
        <header>
            <h1>🟢 CakePHP + Vue + Coolify template</h1>
            <p class="subtitle">If you can see this, your deploy is live.</p>
        </header>

        <div class="card">
            <button type="button" @click="count++">
                Clicked {{ count }} time{{ count === 1 ? '' : 's' }}
            </button>
            <p class="stamp">Rendered at: <code>{{ stamp }}</code></p>
        </div>

        <p class="hint">
            Edit <code>resources/js/components/HelloCoolify.vue</code> to start customising.
        </p>
    </section>
</template>

<style scoped lang="scss">
.hello-coolify {
    max-width: 640px;
    margin: 3rem auto;
    padding: 0 1.5rem;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #1f2937;
}

header h1 {
    margin: 0 0 .5rem;
    font-size: 1.75rem;
}
.subtitle { color: #6b7280; margin: 0; }

.card {
    margin: 2rem 0;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

button {
    background: #2563eb;
    color: white;
    border: 0;
    padding: .6rem 1.2rem;
    font-size: 1rem;
    border-radius: 6px;
    cursor: pointer;
    &:hover { background: #1d4ed8; }
}

.stamp {
    margin: 1rem 0 0;
    font-size: .875rem;
    color: #6b7280;
    code { background: #e5e7eb; padding: 1px 6px; border-radius: 4px; }
}

.hint {
    font-size: .875rem;
    color: #6b7280;
    code { background: #e5e7eb; padding: 1px 6px; border-radius: 4px; }
}
</style>
