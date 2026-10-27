import Alpine from 'alpinejs';

/**
 * Application installable (PWA). Le navigateur n'autorise l'installation et le service worker
 * que sur une connexion sécurisée (HTTPS ou localhost).
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// L'évènement peut arriver avant le démarrage d'Alpine : on le garde de côté.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.__pwaPrompt = event;
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

Alpine.data('pwaInstall', () => ({
    prompt: window.__pwaPrompt ?? null,
    installed: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
    ios: /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
    secure: window.isSecureContext,
    help: false,

    init() {
        window.addEventListener('pwa-installable', () => { this.prompt = window.__pwaPrompt; });
        window.addEventListener('appinstalled', () => { this.installed = true; this.prompt = null; });
    },

    async install() {
        if (!this.prompt) {
            this.help = true;

            return;
        }

        this.prompt.prompt();
        const { outcome } = await this.prompt.userChoice;
        this.prompt = null;
        window.__pwaPrompt = null;

        if (outcome === 'accepted') {
            this.installed = true;
        }
    },
}));

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
 * Formulaire sur une page, en colonnes numérotées (x-form.page / x-form.panel) :
 * toutes les étapes sont visibles à la fois. Chaque colonne indique si elle
 * est complète ; pour un type « formulaire complet », l'envoi reste bloqué
 * tant qu'un champ obligatoire manque.
 */
/** « A », « A et B », « A, B et C ». */
window.formatNames = (names) => new Intl.ListFormat('fr', { style: 'long', type: 'conjunction' }).format(names ?? []);

/**
 * Confirmation avant envoi (fenêtre <x-send-confirmation>), commune aux
 * formulaires d'envoi. Le formulaire n'est soumis qu'après « Confirmer
 * l'envoi » ; « Annuler » ferme la fenêtre sans rien perdre ; un seul envoi
 * est possible (boutons bloqués pendant l'envoi).
 */
const sendConfirmation = () => ({
    confirming: false,
    confirmed: false,
    submitting: false,
    confirmNames: [],
    confirmSubject: '',
    recipientError: false,

    /** À appeler sur l'évènement submit, une fois les champs valides. Renvoie true si l'envoi doit partir. */
    guardSend(event) {
        if (this.submitting) {
            event.preventDefault();

            return false;
        }

        if (this.confirmed) {
            this.submitting = true;

            return true;
        }

        event.preventDefault();
        const names = this.recipientNames();

        if (names.length === 0) {
            this.recipientError = true;

            return false;
        }

        this.recipientError = false;
        this.confirmNames = names;
        this.confirmSubject = this.$root.querySelector('[name="subject"]')?.value?.trim() ?? '';
        this.confirming = true;
        this.$nextTick(() => this.$root.querySelector('[data-confirm-button]')?.focus());

        return false;
    },

    confirmSend() {
        // Nouvelle vérification : le destinataire a pu être retiré entre-temps.
        if (this.recipientNames().length === 0) {
            this.confirming = false;
            this.recipientError = true;

            return;
        }

        this.confirmed = true;
        this.confirming = false;
        this.formElement().requestSubmit();
    },

    cancelSend() {
        if (!this.submitting) {
            this.confirming = false;
        }
    },

    formElement() {
        return this.$root.tagName === 'FORM' ? this.$root : this.$root.querySelector('form');
    },
});

/**
 * Formulaire simple d'envoi (transmission d'un dossier) : le destinataire
 * vient d'une liste (select) ou est fixé (responsable hiérarchique).
 */
Alpine.data('confirmSend', ({ select = null, fixed = null } = {}) => ({
    ...sendConfirmation(),

    recipientNames() {
        if (fixed) {
            return [fixed];
        }

        const field = this.$root.querySelector(select);

        return field && field.value ? [field.selectedOptions[0].text.trim()] : [];
    },

    validate(event) {
        this.guardSend(event);
    },
}));

Alpine.data('formColumns', (count, completeTypes = [], type = '', confirm = false) => ({
    ...sendConfirmation(),
    count,
    confirm,
    completeTypes: completeTypes.map(String),
    type: String(type ?? ''),
    done: Array(count + 1).fill(false),

    recipientNames() {
        return [...this.$root.querySelectorAll('input[name="recipients[]"]')].map((input) => input.dataset.name || input.value);
    },

    get complete() {
        return this.completeTypes.includes(String(this.type));
    },

    get filled() {
        return this.done.filter(Boolean).length;
    },

    get blocked() {
        return this.complete && this.filled < this.count;
    },

    init() {
        const refresh = () => this.$nextTick(() => this.refresh());

        ['input', 'change', 'click'].forEach((name) => this.$root.addEventListener(name, refresh, true));
        this.$watch('type', refresh);
        refresh();
    },

    refresh() {
        for (let n = 1; n <= this.count; n++) {
            this.done[n] = this.fieldsOf(n).every((field) => field.checkValidity());
        }

        if (this.recipientError && this.recipientNames().length > 0) {
            this.recipientError = false;
        }
    },

    fieldsOf(number) {
        const panel = this.$root.querySelector(`[data-step="${number}"]`);

        return panel ? [...panel.querySelectorAll('input, select, textarea')].filter((field) => !field.disabled) : [];
    },

    /** Amène l'utilisateur au premier champ manquant (colonne de gauche d'abord). */
    focusFirstInvalid() {
        for (let n = 1; n <= this.count; n++) {
            const invalid = this.fieldsOf(n).find((field) => !field.checkValidity());

            if (invalid) {
                invalid.closest('[data-step]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                this.$nextTick(() => invalid.reportValidity());

                return true;
            }
        }

        return false;
    },

    validateAll(event) {
        if (this.submitting) {
            event.preventDefault();

            return;
        }

        this.refresh();

        // Aucun destinataire : message explicite, en plus du champ signalé.
        if (this.confirm && this.$root.querySelector('[data-recipient-picker]') && this.recipientNames().length === 0) {
            this.recipientError = true;
        }

        if (this.blocked || this.focusFirstInvalid()) {
            event.preventDefault();

            return;
        }

        if (this.confirm) {
            this.guardSend(event);
        } else {
            this.submitting = true;
        }
    },
}));

/**
 * Section repliable du menu latéral : l'état ouvert / fermé est mémorisé
 * dans le navigateur (si le stockage local est disponible).
 */
Alpine.data('navSection', (key) => ({
    open: true,

    init() {
        try {
            this.open = localStorage.getItem(key) !== 'closed';
        } catch {
            this.open = true;
        }

        this.$watch('open', (value) => {
            try {
                localStorage.setItem(key, value ? 'open' : 'closed');
            } catch {
                // Stockage indisponible (navigation privée) : l'état n'est simplement pas mémorisé.
            }
        });
    },
}));

/**
 * Cloche de notifications : compteur et liste complétés en temps réel par
 * l'évènement « notification-received » (resources/js/realtime.js).
 */
Alpine.data('notificationBell', (unread = 0) => ({
    open: false,
    count: unread,
    live: [],

    get badge() {
        return this.count > 99 ? '99+' : String(this.count);
    },

    init() {
        window.addEventListener('notification-received', (event) => {
            this.live = [event.detail, ...this.live.filter((item) => item.id !== event.detail.id)].slice(0, 5);
            this.count += 1;
        });
    },
}));

window.Alpine = Alpine;
Alpine.start();
