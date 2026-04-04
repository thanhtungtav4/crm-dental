const tabQueryMap = {
    payments: 'payment',
    appointments: 'appointment',
};

const syncTabQuery = (val) => {
    const url = new URL(window.location);
    url.searchParams.set('tab', tabQueryMap[val] ?? val);
    window.history.replaceState({}, '', url);
};

syncTabQuery(activeTab);
ensureActiveTabVisible();

$watch('activeTab', (val) => {
    syncTabQuery(val);
    ensureActiveTabVisible();
});
