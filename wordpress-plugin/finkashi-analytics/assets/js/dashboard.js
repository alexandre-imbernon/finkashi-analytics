/**
 * Script du Dashboard Finkashi Analytics.
 *
 * Architecture :
 *  - api()              : appel d'un endpoint via le proxy WordPress.
 *  - chargerDashboard() : orchestre les cinq appels en parallele puis
 *                         delegue le rendu a des fonctions specialisees.
 *  - calculerIndicateurs / rendre* : fonctions pures de rendu.
 *
 * Aucun appel direct a l'API : tout passe par admin-ajax.php, qui
 * injecte la cle cote serveur.
 */
(function () {
    'use strict';

    // Instances Chart.js conservees pour pouvoir les detruire avant
    // de redessiner (changement de periode).
    const graphes = { trafic: null, canaux: null, sources: null };

    // -----------------------------------------------------------------
    // Appel d'API via le proxy
    // -----------------------------------------------------------------

    async function api(endpoint, parametres = {}) {
        const config = window.finkashiDashboard || {};

        const corps = new FormData();
        corps.append('action',   config.actionProxy);
        corps.append('nonce',    config.nonceProxy);
        corps.append('endpoint', endpoint);
        for (const [cle, valeur] of Object.entries(parametres)) {
            corps.append(cle, String(valeur));
        }

        const reponse = await fetch(config.ajaxUrl, {
            method: 'POST',
            body: corps,
            credentials: 'same-origin',
        });

        const enveloppe = await reponse.json();
        if (!enveloppe.success) {
            const err = new Error(enveloppe.data && enveloppe.data.message || 'Erreur inconnue.');
            err.code = enveloppe.data && enveloppe.data.code;
            throw err;
        }
        return enveloppe.data;
    }

    // -----------------------------------------------------------------
    // Calcul des bornes de date en fonction de la periode choisie
    // -----------------------------------------------------------------

    function bornesPour(jours) {
        const jusque = new Date();
        const depuis = new Date();
        depuis.setDate(depuis.getDate() - (parseInt(jours, 10) - 1));
        return {
            depuis: formaterDate(depuis),
            jusque: formaterDate(jusque),
        };
    }

    function formaterDate(d) {
        const a = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const j = String(d.getDate()).padStart(2, '0');
        return `${a}-${m}-${j}`;
    }

    // -----------------------------------------------------------------
    // Rendu des indicateurs cles
    // -----------------------------------------------------------------

    function calculerIndicateurs(trafic, pays) {
        const totalVisiteurs  = trafic.reduce((s, l) => s + l.visiteurs, 0);
        const totalPagesVues  = trafic.reduce((s, l) => s + l.pages_vues, 0);
        const pagesParVisite  = totalVisiteurs > 0 ? totalPagesVues / totalVisiteurs : 0;
        const nbPays          = pays.length;

        return {
            visiteurs:    totalVisiteurs,
            pagesVues:    totalPagesVues,
            pagesParVisite: pagesParVisite,
            pays:         nbPays,
        };
    }

    function rendreIndicateurs(indicateurs) {
        document.getElementById('stat-visiteurs').textContent       = formaterNombre(indicateurs.visiteurs);
        document.getElementById('stat-pages-vues').textContent      = formaterNombre(indicateurs.pagesVues);
        document.getElementById('stat-pages-par-visite').textContent = indicateurs.pagesParVisite.toFixed(2).replace('.', ',');
        document.getElementById('stat-pays').textContent            = String(indicateurs.pays);
    }

    function formaterNombre(n) {
        return new Intl.NumberFormat('fr-FR').format(n);
    }

    // -----------------------------------------------------------------
    // Graphique de trafic (ligne)
    // -----------------------------------------------------------------

    function rendreTrafic(trafic) {
        const canvas = document.getElementById('graphe-trafic');
        if (!canvas) return;

        if (graphes.trafic) {
            graphes.trafic.destroy();
        }

        graphes.trafic = new Chart(canvas, {
            type: 'line',
            data: {
                labels: trafic.map(l => l.jour),
                datasets: [
                    {
                        label: 'Visiteurs uniques',
                        data: trafic.map(l => l.visiteurs),
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.12)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: 'Pages vues',
                        data: trafic.map(l => l.pages_vues),
                        borderColor: '#00a32a',
                        borderDash: [4, 3],
                        backgroundColor: 'transparent',
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 } } },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    }

    // -----------------------------------------------------------------
    // Graphique des canaux (donut)
    // -----------------------------------------------------------------

    const LIBELLES_CANAUX = {
        recherche: 'Recherche',
        social:    'Reseaux sociaux',
        referent:  'Referent',
        direct:    'Direct',
    };
    const COULEURS_CANAUX = ['#2271b1', '#00a32a', '#dba617', '#787c82'];

    function rendreCanaux(canaux) {
        const canvas = document.getElementById('graphe-canaux');
        if (!canvas) return;

        if (graphes.canaux) {
            graphes.canaux.destroy();
        }

        graphes.canaux = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: canaux.map(l => LIBELLES_CANAUX[l.canal] || l.canal),
                datasets: [{
                    data: canaux.map(l => l.visiteurs),
                    backgroundColor: COULEURS_CANAUX.slice(0, canaux.length),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 12 }, boxWidth: 12 } },
                },
                cutout: '60%',
            },
        });
    }

    // -----------------------------------------------------------------
    // Graphique des sources (barres horizontales)
    // -----------------------------------------------------------------

    function rendreSources(sources) {
        const canvas = document.getElementById('graphe-sources');
        if (!canvas) return;

        if (graphes.sources) {
            graphes.sources.destroy();
        }

        const couleursParCanal = {
            recherche: '#2271b1',
            social:    '#00a32a',
            referent:  '#dba617',
            direct:    '#787c82',
        };

        graphes.sources = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: sources.map(s => s.domaine),
                datasets: [{
                    label: 'Visiteurs',
                    data: sources.map(s => s.visiteurs),
                    backgroundColor: sources.map(s => couleursParCanal[s.canal] || '#787c82'),
                    borderRadius: 2,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    }

    // -----------------------------------------------------------------
    // Tableaux : pages et pays
    // -----------------------------------------------------------------

    function rendrePages(pages) {
        const corps = document.getElementById('tableau-pages-corps');
        if (!corps) return;

        if (pages.length === 0) {
            corps.innerHTML = '<tr><td colspan="3" class="finkashi-vide">Aucune donnee sur la periode.</td></tr>';
            return;
        }

        corps.innerHTML = pages.map(p => `
            <tr>
                <td>
                    <strong>${echapper(p.titre || '(sans titre)')}</strong>
                    <div class="finkashi-chemin">${echapper(p.chemin)}</div>
                </td>
                <td class="num">${formaterNombre(p.visiteurs)}</td>
                <td class="num">${formaterNombre(p.pages_vues)}</td>
            </tr>
        `).join('');
    }

    function rendrePays(pays) {
        const corps = document.getElementById('tableau-pays-corps');
        if (!corps) return;

        if (pays.length === 0) {
            corps.innerHTML = '<tr><td colspan="3" class="finkashi-vide">Aucune donnee sur la periode.</td></tr>';
            return;
        }

        const total = pays.reduce((s, l) => s + l.visiteurs, 0);

        corps.innerHTML = pays.map(p => {
            const part = total > 0 ? Math.round(p.visiteurs * 100 / total) : 0;
            return `
                <tr>
                    <td>${echapper(p.pays)}</td>
                    <td class="num">${formaterNombre(p.visiteurs)}</td>
                    <td class="num">${part}%</td>
                </tr>
            `;
        }).join('');
    }

    function echapper(texte) {
        const div = document.createElement('div');
        div.textContent = String(texte);
        return div.innerHTML;
    }

    // -----------------------------------------------------------------
    // Orchestration
    // -----------------------------------------------------------------

    async function chargerDashboard(jours) {
        const bornes = bornesPour(jours);
        const zone   = document.getElementById('finkashi-dashboard-zone');
        const erreur = document.getElementById('finkashi-erreur');

        zone.classList.add('chargement');
        erreur.style.display = 'none';

        try {
            // Cinq appels en parallele.
            const [trafic, pages, canaux, sources, pays] = await Promise.all([
                api('trafic',  bornes),
                api('pages',   { ...bornes, limite: 10 }),
                api('canaux',  bornes),
                api('sources', { ...bornes, limite: 10 }),
                api('pays',    bornes),
            ]);

            rendreIndicateurs(calculerIndicateurs(trafic, pays));
            rendreTrafic(trafic);
            rendreCanaux(canaux);
            rendreSources(sources);
            rendrePages(pages);
            rendrePays(pays);
        } catch (e) {
            erreur.textContent = e.message;
            erreur.style.display = 'block';
            if (e.code === 'NON_CONFIGURE') {
                erreur.innerHTML += ' <a href="' + (window.finkashiDashboard?.urlReglages || '#') + '">Aller aux reglages</a>';
            }
        } finally {
            zone.classList.remove('chargement');
        }
    }

    // -----------------------------------------------------------------
    // Initialisation
    // -----------------------------------------------------------------

    function initialiser() {
        const selecteur = document.getElementById('finkashi-periode');
        if (!selecteur) return;

        if (typeof Chart === 'undefined') {
            document.getElementById('finkashi-erreur').textContent =
                'La bibliotheque Chart.js n\'a pas pu etre chargee.';
            document.getElementById('finkashi-erreur').style.display = 'block';
            return;
        }

        selecteur.addEventListener('change', function () {
            chargerDashboard(selecteur.value);
        });

        // Premier chargement.
        chargerDashboard(selecteur.value);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();
