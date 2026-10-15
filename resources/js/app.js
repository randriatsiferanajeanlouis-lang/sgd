import Alpine from 'alpinejs';
import { BarController, BarElement, CategoryScale, Chart, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, BarController, BarElement, LinearScale, CategoryScale, Filler, Tooltip);

/**
 * Thème clair/sombre : appliqué immédiatement, mémorisé dans le navigateur
 * et, pour un utilisateur connecté, enregistré sur son compte.
 */
Alpine.data('themeToggle', () => ({
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = !this.dark;
        const theme = this.dark ? 'dark' : 'light';

        document.documentElement.classList.toggle('dark', this.dark);

        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            // Stockage indisponible (navigation privée) : le choix reste valable pour la page.
        }

        const url = this.$el.dataset.url;
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        if (url && token) {
            fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ theme }),
            });
        }
    },
}));

/**
 * Choix des destinataires : département → utilisateur, plusieurs possibles.
 * Le serveur revalide chaque identifiant envoyé.
 */
Alpine.data('recipientPicker', (directory, initial = []) => ({
    directory,
    departmentId: '',
    search: '',
    selected: [],

    init() {
        const wanted = initial.map(Number);
        this.selected = this.allUsers().filter((user) => wanted.includes(user.id));
    },

    allUsers() {
        return this.directory.flatMap((department) =>
            department.users.map((user) => ({ ...user, department: department.name, departmentId: department.id })),
        );
    },

    /**
     * Personnes proposées : celles du département choisi, ou de tous les
     * départements dès qu'un nom est tapé (règle commune matchesSearch).
     */
    get availableUsers() {
        if (!this.departmentId && !this.search.trim()) {
            return [];
        }

        const pool = this.departmentId
            ? this.allUsers().filter((user) => String(user.departmentId) === String(this.departmentId))
            : this.allUsers();

        return pool.filter((user) => matchesSearch(`${user.name} ${user.job_title ?? ''}`, this.search));
    },

    addFirst() {
        const user = this.availableUsers.find((item) => !this.isSelected(item));

        if (user) {
            this.selected.push(user);
            this.search = '';
        }
    },

    isSelected(user) {
        return this.selected.some((item) => item.id === user.id);
    },

    toggle(user) {
        this.isSelected(user) ? this.remove(user) : this.selected.push(user);
    },

    remove(user) {
        this.selected = this.selected.filter((item) => item.id !== user.id);
    },
}));

/**
 * Formulaire par étapes (x-form.modal) : « Suivant » n'avance que si les champs
 * de l'étape sont valides ; à l'envoi, la première étape incomplète est rouverte.
 * « complete » : le type choisi exige un formulaire complet (ex. Sponsoring).
 */
Alpine.data('formSteps', (count, start = 1, completeTypes = [], type = '') => ({
    step: start,
    count,
    completeTypes: completeTypes.map(String),
    type: String(type ?? ''),
    stepValid: false,

    get complete() {
        return this.completeTypes.includes(String(this.type));
    },

    /**
     * « Suivant » (et l'envoi) restent grisés tant que l'étape n'est pas remplie,
     * pour les types à formulaire complet ; les autres (ex. demande de stage)
     * gardent un bouton cliquable, qui signale seulement les champs de base manquants.
     */
    get blocked() {
        return this.complete && !this.stepValid;
    },

    init() {
        const refresh = () => this.$nextTick(() => this.refresh());

        ['input', 'change', 'click'].forEach((name) => this.$root.addEventListener(name, refresh, true));
        this.$watch('step', refresh);
        this.$watch('type', refresh);
        refresh();
    },

    refresh() {
        this.stepValid = this.fieldsOf(this.step).every((field) => field.checkValidity());
    },

    fieldsOf(number) {
        const container = this.$root.querySelector(`[data-step="${number}"]`);

        return container ? [...container.querySelectorAll('input, select, textarea')].filter((field) => !field.disabled) : [];
    },

    /** Vérifie une étape ; si un champ manque, affiche l'étape et le message du navigateur. */
    check(number) {
        const invalid = this.fieldsOf(number).find((field) => !field.checkValidity());

        if (!invalid) {
            return true;
        }

        this.step = number;
        this.$nextTick(() => invalid.reportValidity());

        return false;
    },

    next() {
        if (this.blocked) {
            return;
        }

        if (this.check(this.step)) {
            this.goTo(this.step + 1);
        }
    },

    previous() {
        this.goTo(this.step - 1);
    },

    /** Revenir en arrière est libre ; avancer exige que les étapes précédentes soient complètes. */
    goTo(number) {
        const target = Math.min(Math.max(number, 1), this.count);

        for (let n = 1; n < target; n++) {
            if (!this.check(n)) {
                return;
            }
        }

        this.step = target;
        this.$root.scrollTo({ top: 0, behavior: 'smooth' });
    },

    validateAll(event) {
        if (this.count < 2) {
            return;
        }

        if (this.blocked) {
            event.preventDefault();

            return;
        }

        // Entrée dans une étape intermédiaire : passer à l'étape suivante au lieu d'envoyer.
        if (this.step < this.count) {
            event.preventDefault();
            this.next();

            return;
        }

        for (let n = 1; n <= this.count; n++) {
            if (!this.check(n)) {
                event.preventDefault();

                return;
            }
        }
    },
}));

/**
 * Proposition d'échéance à partir du niveau d'urgence (délai en heures),
 * tant que l'utilisateur n'a pas choisi lui-même une date.
 */
Alpine.data('dueDateHelper', (levels, initialDate = '') => ({
    levels,
    dueDate: initialDate,
    touched: initialDate !== '',

    suggest(levelId) {
        const level = this.levels.find((item) => String(item.id) === String(levelId));

        if (!level || this.touched) {
            return;
        }

        const date = new Date();
        date.setHours(date.getHours() + level.response_delay_hours);
        this.dueDate = date.toISOString().slice(0, 10);
    },
}));

/**
 * Aperçu de l'échéance d'une demande externe : date de réception + délai
 * de l'urgence. Le calcul qui fait foi est refait par le serveur.
 */
Alpine.data('externalDueDate', (levels, receivedAt, urgencyId = '') => ({
    levels,
    receivedAt,
    urgencyId: String(urgencyId ?? ''),

    get preview() {
        const level = this.levels.find((item) => String(item.id) === this.urgencyId);

        if (!level || !this.receivedAt) {
            return 'Choisissez la date de réception et l’urgence.';
        }

        const now = new Date();
        const due = new Date(`${this.receivedAt}T${now.toTimeString().slice(0, 8)}`);
        due.setHours(due.getHours() + level.response_delay_hours);

        return due.toLocaleString('fr-FR', { dateStyle: 'full', timeStyle: 'short' });
    },
}));

/**
 * Règle de recherche commune (identique au serveur, App\Support\Search) :
 * une seule lettre → un mot qui commence par cette lettre ; deux caractères
 * ou plus → le texte contient la saisie. Casse et accents sont ignorés.
 */
const normalize = (text) => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function matchesSearch(text, term) {
    const needle = normalize(term.trim());

    if (needle === '') {
        return true;
    }

    const haystack = normalize(text);

    return needle.length === 1
        ? haystack.split(/[^a-z0-9]+/).some((word) => word.startsWith(needle))
        : haystack.includes(needle);
}

window.matchesSearch = matchesSearch;

/**
 * Barre de recherche avec propositions instantanées sous la zone de saisie.
 * Les propositions viennent de la page de liste elle-même (?suggest=1), avec
 * les autres filtres du formulaire : le serveur applique les mêmes droits.
 */
Alpine.data('liveSearch', (initial = '') => ({
    term: initial,
    results: [],
    open: false,
    loading: false,
    active: -1,
    timer: null,
    request: null,

    lookup() {
        clearTimeout(this.timer);

        if (this.term.trim() === '') {
            this.results = [];
            this.open = false;
            return;
        }

        this.open = true;
        this.loading = true;
        this.timer = setTimeout(() => this.fetchResults(), 200);
    },

    async fetchResults() {
        const term = this.term.trim();
        const form = this.$root.closest('form');
        const params = new URLSearchParams(form ? new FormData(form) : undefined);

        params.set('search', term);
        params.set('suggest', '1');
        params.delete('page');

        this.request?.abort();
        this.request = new AbortController();

        try {
            const response = await fetch(`${form?.getAttribute('action') || window.location.pathname}?${params}`, {
                headers: { Accept: 'application/json' },
                signal: this.request.signal,
            });

            if (!response.ok) {
                throw new Error(response.status);
            }

            if (term === this.term.trim()) {
                this.results = (await response.json()).results;
                this.active = -1;
                this.loading = false;
            }
        } catch (e) {
            if (e.name !== 'AbortError') {
                this.results = [];
                this.loading = false;
            }
        }
    },

    move(step) {
        if (!this.open || this.results.length === 0) {
            return;
        }

        this.active = (this.active + step + this.results.length) % this.results.length;
    },

    choose(event) {
        if (this.open && this.results[this.active]) {
            event.preventDefault();
            window.location.href = this.results[this.active].url;
        }
    },

    close() {
        this.open = false;
        this.active = -1;
    },

    /**
     * Découpe le libellé pour surligner la partie qui correspond à la saisie.
     */
    highlight(text) {
        const needle = normalize(this.term.trim());
        const chars = [...String(text)];
        const folded = chars.map((char) => normalize(char));
        let start = -1;

        for (let i = 0; i + needle.length <= chars.length && needle !== ''; i++) {
            const wordStart = i === 0 || !/[a-z0-9]/.test(folded[i - 1]);

            if ((needle.length > 1 || wordStart) && folded.slice(i, i + needle.length).join('') === needle) {
                start = i;
                break;
            }
        }

        if (start < 0) {
            return [{ text, match: false }];
        }

        return [
            { text: chars.slice(0, start).join(''), match: false },
            { text: chars.slice(start, start + needle.length).join(''), match: true },
            { text: chars.slice(start + needle.length).join(''), match: false },
        ].filter((part) => part.text !== '');
    },
}));

/**
 * Courbes du tableau de bord : une courbe par département, couleur fixe par
 * département (clair / sombre), période Semaine / Mois / Année.
 * L'instance Chart.js reste hors de l'état Alpine (les proxys la casseraient).
 */
Alpine.data('trendChart', (trends, initialPeriod = 'month') => {
    let chart = null;

    const isDark = () => document.documentElement.classList.contains('dark');

    const withAlpha = (hex, alpha) => {
        const value = parseInt(hex.slice(1), 16);

        return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
    };

    // Ligne verticale qui suit le survol (repère de lecture).
    const crosshair = {
        id: 'crosshair',
        afterDatasetsDraw(instance) {
            const active = instance.tooltip?.getActiveElements?.() ?? [];

            if (!active.length) {
                return;
            }

            const { ctx, chartArea } = instance;
            const x = active[0].element.x;

            ctx.save();
            ctx.strokeStyle = isDark() ? 'rgba(255,255,255,0.25)' : 'rgba(15,23,42,0.2)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.stroke();
            ctx.restore();
        },
    };

    return {
        trends,
        period: initialPeriod,
        dark: isDark(),

        get current() {
            return this.trends.periods[this.period];
        },

        colorOf(serie) {
            return this.dark ? serie.color.dark : serie.color.light;
        },

        init() {
            this.render();
            this.$watch('period', () => this.render());

            // Changement de thème : légende et courbes reprennent les teintes du mode choisi.
            new MutationObserver(() => {
                if (this.dark !== isDark()) {
                    this.dark = isDark();
                    this.render();
                }
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        render() {
            const dark = isDark();
            const period = this.current;
            const single = period.series.length === 1;
            const surface = dark ? '#121238' : '#ffffff';
            const ink = dark ? '#c9c9ea' : '#555968';
            // Quadrillage (carreaux) : lignes verticales et horizontales visibles mais discrètes.
            const grid = dark ? 'rgba(255,255,255,0.12)' : 'rgba(26,26,140,0.10)';

            const datasets = period.series.map((serie) => {
                const color = dark ? serie.color.dark : serie.color.light;

                return {
                    label: serie.name,
                    data: serie.data,
                    borderColor: color,
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHitRadius: 12,
                    pointHoverBackgroundColor: color,
                    pointHoverBorderColor: surface,
                    pointHoverBorderWidth: 2,
                    fill: 'origin',
                    backgroundColor: (context) => {
                        const area = context.chart.chartArea;

                        if (!area) {
                            return 'transparent';
                        }

                        const gradient = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                        gradient.addColorStop(0, withAlpha(color, single ? 0.35 : 0.12));
                        gradient.addColorStop(1, withAlpha(color, 0));

                        return gradient;
                    },
                };
            });

            if (chart) {
                chart.destroy();
            }

            chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: { labels: period.labels, datasets },
                plugins: [crosshair],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 300 },
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: {
                            grid: { color: grid, lineWidth: 1, drawTicks: false },
                            border: { display: true, color: grid },
                            ticks: { color: ink, maxRotation: 0, autoSkip: true, maxTicksLimit: 8, padding: 6, font: { size: 11 } },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: grid, lineWidth: 1, drawTicks: false },
                            border: { display: true, color: grid },
                            ticks: { color: ink, precision: 0, maxTicksLimit: 7, padding: 6, font: { size: 11 } },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: dark ? '#1c1c4a' : '#ffffff',
                            titleColor: dark ? '#ffffff' : '#202334',
                            bodyColor: dark ? '#c9c9ea' : '#555968',
                            borderColor: dark ? '#2c2c5e' : '#dde0ee',
                            borderWidth: 1,
                            padding: 10,
                            boxPadding: 4,
                            usePointStyle: true,
                            callbacks: {
                                label: (item) => ` ${item.dataset.label} : ${item.formattedValue} dossier(s)`,
                                labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
                            },
                        },
                    },
                },
            });
        },
    };
});

/**
 * Graphique à barres (statistiques) : une mesure par graphique, une barre par catégorie.
 * config = { labels, values, colors?: [{light, dark}] (une par barre, sinon bleu unique),
 *            horizontal?: bool, unit?: 'dossier(s)' }.
 */
Alpine.data('barChart', (config) => {
    let chart = null;

    const isDark = () => document.documentElement.classList.contains('dark');
    const single = { light: '#1a1a8c', dark: '#a5a5f5' };

    return {
        init() {
            this.render();

            new MutationObserver(() => this.render()).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        render() {
            const dark = isDark();
            const ink = dark ? '#c9c9ea' : '#555968';
            const grid = dark ? 'rgba(255,255,255,0.12)' : 'rgba(26,26,140,0.10)';
            const colors = config.values.map((_, index) => {
                const color = config.colors?.[index] ?? single;

                return dark ? color.dark : color.light;
            });
            const valueAxis = { beginAtZero: true, grid: { color: grid, drawTicks: false }, border: { display: true, color: grid }, ticks: { color: ink, precision: 0, padding: 6, font: { size: 11 } } };
            const categoryAxis = { grid: { display: false }, border: { display: true, color: grid }, ticks: { color: ink, padding: 6, font: { size: 11 }, autoSkip: false } };

            if (chart) {
                chart.destroy();
            }

            chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels: config.labels,
                    datasets: [{
                        data: config.values,
                        backgroundColor: colors,
                        hoverBackgroundColor: colors,
                        borderRadius: 4,
                        borderSkipped: 'start',
                        maxBarThickness: 32,
                        categoryPercentage: 0.7,
                    }],
                },
                options: {
                    indexAxis: config.horizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 300 },
                    scales: config.horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: dark ? '#1c1c4a' : '#ffffff',
                            titleColor: dark ? '#ffffff' : '#202334',
                            bodyColor: dark ? '#c9c9ea' : '#555968',
                            borderColor: dark ? '#2c2c5e' : '#dde0ee',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: false,
                            callbacks: { label: (item) => `${item.formattedValue} ${config.unit ?? ''}`.trim() },
                        },
                    },
                },
            });
        },
    };
});

window.Alpine = Alpine;
Alpine.start();
