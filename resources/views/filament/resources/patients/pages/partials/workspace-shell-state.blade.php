{
    activeTab: $wire.entangle('activeTab'),
    copiedMessage: null,
    copyTimer: null,
    ensureActiveTabVisible() {
        this.$nextTick(() => {
            const tabs = this.$refs.topTabs;
            if (! tabs) {
                return;
            }

            const active = tabs.querySelector('.crm-top-tab.is-active');
            if (! active) {
                return;
            }

            const tabItems = Array.from(tabs.querySelectorAll('.crm-top-tab'));
            const activeIndex = tabItems.indexOf(active);

            if (activeIndex <= 1) {
                tabs.scrollTo({
                    left: 0,
                    behavior: 'auto',
                });

                return;
            }

            if (activeIndex >= (tabItems.length - 2)) {
                tabs.scrollTo({
                    left: tabs.scrollWidth,
                    behavior: 'auto',
                });

                return;
            }

            active.scrollIntoView({
                behavior: 'auto',
                block: 'nearest',
                inline: 'center',
            });
        });
    },
    copyToClipboard(value, label) {
        if (! value) {
            return;
        }

        const writeClipboard = () => {
            if (window.navigator?.clipboard?.writeText) {
                return window.navigator.clipboard.writeText(value);
            }

            const tempInput = document.createElement('textarea');
            tempInput.value = value;
            tempInput.setAttribute('readonly', '');
            tempInput.style.position = 'fixed';
            tempInput.style.opacity = '0';
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            return Promise.resolve();
        };

        writeClipboard().then(() => {
            this.copiedMessage = `${label} đã được sao chép`;

            if (this.copyTimer) {
                window.clearTimeout(this.copyTimer);
            }

            this.copyTimer = window.setTimeout(() => {
                this.copiedMessage = null;
            }, 1400);
        });
    },
}
