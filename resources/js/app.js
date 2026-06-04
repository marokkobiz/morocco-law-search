import './bootstrap';

const formatLandingNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));

const setLandingMetric = (id, value) => {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = formatLandingNumber(value);
    }
};

if (document.querySelector('.legal-landing')) {
    Promise.all([
        fetch('/api/laws/overview').then((response) => response.ok ? response.json() : null).catch(() => null),
        fetch('/api/corpus/status').then((response) => response.ok ? response.json() : null).catch(() => null),
    ]).then(([overview, corpus]) => {
        setLandingMetric('landing-article-count', overview?.totalArticles || corpus?.activeArticles);
        setLandingMetric('landing-source-count', corpus?.totalSources || overview?.totalDocuments);
    });
}
