/**
 * Device selection UI components.
 *
 * MultiDeviceSelector  – multiple devices can be selected simultaneously.
 * SingleDeviceSelector – only one device selected at a time.
 *
 * Both render badge pills with a remove button inside a container element,
 * and call an onChange callback whenever the selection changes.
 */

class MultiDeviceSelector {
    /**
     * @param {object}   opts
     * @param {string}   opts.containerId    ID of the badge-list container element
     * @param {string}   opts.itemSelector   CSS selector for clickable dropdown items
     * @param {string}   opts.msgEmpty       Text shown when nothing is selected
     * @param {string}   [opts.msgClass]     CSS class for the empty-state span
     * @param {Function} opts.getData        fn(el) → { key, label }
     * @param {boolean}  [opts.hideSelected] Hide items in dropdown when selected (default false)
     * @param {string}   [opts.badgeClass]   CSS classes for each badge span
     * @param {string}   [opts.closeBtnClass] CSS classes for the remove button
     * @param {Function} opts.onChange       fn(selections) called after each change
     */
    constructor({
        containerId,
        itemSelector,
        msgEmpty,
        msgClass      = 'text-danger fst-italic',
        getData,
        hideSelected  = false,
        badgeClass    = 'badge bg-light text-dark border d-flex align-items-center',
        closeBtnClass = 'btn-close btn-sm ms-2',
        onChange,
    }) {
        this.container     = document.getElementById(containerId);
        this.itemSelector  = itemSelector;
        this.msgEmpty      = msgEmpty;
        this.msgClass      = msgClass;
        this.getData       = getData;
        this.hideSelected  = hideSelected;
        this.badgeClass    = badgeClass;
        this.closeBtnClass = closeBtnClass;
        this.onChange      = onChange;
        this.selections    = [];

        document.querySelectorAll(itemSelector).forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                const { key, label } = getData(el);
                const idx = this.selections.findIndex(s => s.key === key);
                if (idx >= 0) {
                    this.selections.splice(idx, 1);
                } else {
                    this.selections.push({ key, label });
                }
                this._render();
                this.onChange(this.selections);
            });
        });

        this._render();
    }

    _render() {
        const cont = this.container;
        cont.innerHTML = '';

        if (this.hideSelected) {
            document.querySelectorAll(this.itemSelector).forEach(el => {
                el.parentElement.style.display = 'block';
            });
        }

        if (!this.selections.length) {
            const span = document.createElement('span');
            span.className = this.msgClass;
            span.textContent = this.msgEmpty;
            cont.appendChild(span);
            return;
        }

        this.selections.forEach(sel => {
            const badge = document.createElement('span');
            badge.className = this.badgeClass;
            badge.textContent = sel.label;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = this.closeBtnClass;
            btn.addEventListener('click', () => {
                this.selections = this.selections.filter(s => s.key !== sel.key);
                this._render();
                this.onChange(this.selections);
            });

            badge.appendChild(btn);
            cont.appendChild(badge);

            if (this.hideSelected) {
                const el = document.querySelector(`${this.itemSelector}[data-url="${sel.key}"]`);
                if (el) el.parentElement.style.display = 'none';
            }
        });
    }
}

class SingleDeviceSelector {
    /**
     * @param {object}   opts
     * @param {string}   opts.containerId    ID of the badge container element
     * @param {string}   opts.itemSelector   CSS selector for clickable dropdown items
     * @param {string}   opts.msgEmpty       Text shown when nothing is selected
     * @param {string}   [opts.msgClass]     CSS class for the empty-state span
     * @param {Function} opts.getData        fn(el) → { key, label }
     * @param {string}   [opts.badgeClass]   CSS classes for the badge span
     * @param {string}   [opts.closeBtnClass] CSS classes for the remove button
     * @param {Function} opts.onChange       fn(selection | null) called after each change
     */
    constructor({
        containerId,
        itemSelector,
        msgEmpty,
        msgClass      = 'text-danger fst-italic',
        getData,
        badgeClass    = 'badge bg-light text-dark border d-flex align-items-center',
        closeBtnClass = 'btn-close btn-sm ms-2',
        onChange,
    }) {
        this.container     = document.getElementById(containerId);
        this.itemSelector  = itemSelector;
        this.msgEmpty      = msgEmpty;
        this.msgClass      = msgClass;
        this.getData       = getData;
        this.badgeClass    = badgeClass;
        this.closeBtnClass = closeBtnClass;
        this.onChange      = onChange;
        this.selection     = null;

        document.querySelectorAll(itemSelector).forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                this.selection = getData(el);
                this._render();
                this.onChange(this.selection);
            });
        });

        this._render();
    }

    _render() {
        const cont = this.container;
        cont.innerHTML = '';

        if (!this.selection) {
            const span = document.createElement('span');
            span.className = this.msgClass;
            span.textContent = this.msgEmpty;
            cont.appendChild(span);
            return;
        }

        const badge = document.createElement('span');
        badge.className = this.badgeClass;
        badge.textContent = this.selection.label;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = this.closeBtnClass;
        btn.addEventListener('click', () => {
            this.selection = null;
            this._render();
            this.onChange(null);
        });

        badge.appendChild(btn);
        cont.appendChild(badge);
    }
}
