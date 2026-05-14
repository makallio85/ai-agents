import { createApp } from 'vue';
import HelloCoolify from './components/HelloCoolify.vue';
import '../scss/app.scss';

const mountEl = document.getElementById('app');
if (mountEl) {
    createApp(HelloCoolify).mount(mountEl);
}
