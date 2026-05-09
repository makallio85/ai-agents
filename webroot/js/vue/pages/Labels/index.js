(function () {
    'use strict';

    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var watch = Vue.watch;
    var onMounted = Vue.onMounted;

    createApp({
        setup: function () {
            var labels = ref([]);
            var loading = ref(true);
            var showCreateModal = ref(false);
            var creating = ref(false);
            var createError = ref('');

            var newLabel = ref({
                name: '',
                slug: '',
                color: '#0075ca',
                description: '',
                keywordsRaw: ''
            });

            // Auto-generate slug from name
            watch(function () { return newLabel.value.name; }, function (val) {
                newLabel.value.slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            });

            async function loadLabels() {
                loading.value = true;
                try {
                    var data = await Api.labels.index();
                    labels.value = data.data || [];
                } catch (e) {
                    // silent
                } finally {
                    loading.value = false;
                }
            }

            async function createLabel() {
                createError.value = '';
                if (!newLabel.value.name || !newLabel.value.slug) {
                    createError.value = 'Name and slug are required.';
                    return;
                }
                creating.value = true;
                try {
                    var keywords = newLabel.value.keywordsRaw
                        ? newLabel.value.keywordsRaw.split(',').map(function (k) { return k.trim(); }).filter(Boolean)
                        : [];
                    await Api.labels.create({
                        name: newLabel.value.name,
                        slug: newLabel.value.slug,
                        color: newLabel.value.color,
                        description: newLabel.value.description || null,
                        keywords: JSON.stringify(keywords)
                    });
                    showCreateModal.value = false;
                    newLabel.value = { name: '', slug: '', color: '#0075ca', description: '', keywordsRaw: '' };
                    await loadLabels();
                } catch (e) {
                    createError.value = (e && e.message) ? e.message : 'Failed to create label.';
                } finally {
                    creating.value = false;
                }
            }

            async function deleteLabel(id) {
                if (!confirm('Delete this label?')) { return; }
                try {
                    await Api.labels.del(id);
                    labels.value = labels.value.filter(function (l) { return l.id !== id; });
                } catch (e) {
                    alert('Failed to delete label.');
                }
            }

            function parseKeywords(raw) {
                if (!raw) { return []; }
                try { return JSON.parse(raw); } catch (e) { return []; }
            }

            function contrastColor(hex) {
                // Returns black or white depending on background luminance
                if (!hex || hex.length < 7) { return '#000'; }
                var r = parseInt(hex.slice(1, 3), 16);
                var g = parseInt(hex.slice(3, 5), 16);
                var b = parseInt(hex.slice(5, 7), 16);
                var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5 ? '#000' : '#fff';
            }

            onMounted(loadLabels);

            return {
                labels: labels,
                loading: loading,
                showCreateModal: showCreateModal,
                creating: creating,
                createError: createError,
                newLabel: newLabel,
                createLabel: createLabel,
                deleteLabel: deleteLabel,
                parseKeywords: parseKeywords,
                contrastColor: contrastColor
            };
        }
    }).mount('#labels-app');
})();
