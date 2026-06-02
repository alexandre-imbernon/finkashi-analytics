/**
 * Script de la page Alertes.
 *
 * Gere :
 *  - le chargement de la liste des regles et de l'historique,
 *  - l'ouverture de la modale de creation / edition,
 *  - les operations de creation, modification, suppression, bascule
 *    d'etat actif/inactif,
 *  - les rafraichissements apres chaque operation.
 *
 * Tous les appels passent par le proxy WordPress (window.finkashiAlertes).
 */
(function () {
    'use strict';

    const LIBELLES_METRIQUE = {
        visiteurs_jour:   'Visiteurs / jour',
        pages_vues_jour:  'Pages vues / jour',
    };
    const LIBELLES_OPERATEUR = {
        inferieur: 'Inferieur a',
        superieur: 'Superieur a',
    };

    // -----------------------------------------------------------------
    // Appel d'API via le proxy
    // -----------------------------------------------------------------

    async function api(endpoint, methode, options = {}) {
        const config = window.finkashiAlertes || {};

        const corps = new FormData();
        corps.append('action',   config.actionProxy);
        corps.append('nonce',    config.nonceProxy);
        corps.append('endpoint', endpoint);
        corps.append('methode',  methode);
        if (options.id) {
            corps.append('id', String(options.id));
        }
        if (options.body) {
            corps.append('corps', JSON.stringify(options.body));
        }
        if (options.params) {
            for (const [k, v] of Object.entries(options.params)) {
                corps.append(k, String(v));
            }
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
    // Rendu des tableaux
    // -----------------------------------------------------------------

    function echapper(texte) {
        const div = document.createElement('div');
        div.textContent = String(texte ?? '');
        return div.innerHTML;
    }

    function rendreRegles(regles) {
        const corps = document.getElementById('tableau-regles-corps');
        if (!corps) return;

        if (regles.length === 0) {
            corps.innerHTML = '<tr><td colspan="5" class="finkashi-vide">Aucune regle configuree.</td></tr>';
            return;
        }

        corps.innerHTML = regles.map(r => `
            <tr data-id="${r.id}">
                <td>
                    <span class="finkashi-badge ${r.active ? 'badge-actif' : 'badge-inactif'}">
                        ${r.active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>${echapper(LIBELLES_METRIQUE[r.metrique] || r.metrique)}</td>
                <td>${echapper(LIBELLES_OPERATEUR[r.operateur] || r.operateur)}</td>
                <td class="num">${r.seuil}</td>
                <td class="actions">
                    <button type="button" class="button-link finkashi-modifier">Modifier</button>
                    &middot;
                    <button type="button" class="button-link finkashi-basculer">
                        ${r.active ? 'Desactiver' : 'Activer'}
                    </button>
                    &middot;
                    <button type="button" class="button-link finkashi-supprimer">Supprimer</button>
                </td>
            </tr>
        `).join('');
    }

    function rendreHistorique(declenchements) {
        const corps = document.getElementById('tableau-historique-corps');
        if (!corps) return;

        if (declenchements.length === 0) {
            corps.innerHTML = '<tr><td colspan="4" class="finkashi-vide">Aucun declenchement sur la periode.</td></tr>';
            return;
        }

        corps.innerHTML = declenchements.map(d => {
            const metrique = LIBELLES_METRIQUE[d.metrique] || d.metrique;
            const operateur = LIBELLES_OPERATEUR[d.operateur] || d.operateur;
            const date = formaterDateHeure(d.declenchee_le);
            return `
                <tr>
                    <td>${echapper(date)}</td>
                    <td>${echapper(metrique)} <span class="finkashi-condition">${echapper(operateur.toLowerCase())} ${d.seuil}</span></td>
                    <td class="num">${d.valeur_constatee}</td>
                    <td>
                        <span class="finkashi-badge ${d.notifiee ? 'badge-actif' : 'badge-inactif'}">
                            ${d.notifiee ? 'Notifiee' : 'En attente'}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function formaterDateHeure(iso) {
        // L'API renvoie "YYYY-MM-DD HH:MM:SS" en UTC. On le rend en local
        // sans librairie : split simple.
        const match = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (!match) return iso;
        return `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}`;
    }

    // -----------------------------------------------------------------
    // Modale : ouverture / fermeture / soumission
    // -----------------------------------------------------------------

    function ouvrirModale(regle) {
        document.getElementById('finkashi-modale-titre').textContent =
            regle ? 'Modifier la regle' : 'Nouvelle regle d\'alerte';
        document.getElementById('finkashi-regle-id').value         = regle ? regle.id : '';
        document.getElementById('finkashi-regle-metrique').value   = regle ? regle.metrique : 'visiteurs_jour';
        document.getElementById('finkashi-regle-operateur').value  = regle ? regle.operateur : 'inferieur';
        document.getElementById('finkashi-regle-seuil').value      = regle ? regle.seuil : 10;
        document.getElementById('finkashi-regle-active').checked   = regle ? regle.active : true;
        document.getElementById('finkashi-modale-erreur').style.display = 'none';
        document.getElementById('finkashi-modale').style.display = 'flex';
    }

    function fermerModale() {
        document.getElementById('finkashi-modale').style.display = 'none';
    }

    async function enregistrerModale() {
        const id = document.getElementById('finkashi-regle-id').value;
        const erreur = document.getElementById('finkashi-modale-erreur');
        erreur.style.display = 'none';

        const corps = {
            metrique:  document.getElementById('finkashi-regle-metrique').value,
            operateur: document.getElementById('finkashi-regle-operateur').value,
            seuil:     parseInt(document.getElementById('finkashi-regle-seuil').value, 10),
            active:    document.getElementById('finkashi-regle-active').checked,
        };

        if (isNaN(corps.seuil) || corps.seuil < 0) {
            erreur.textContent = 'Le seuil doit etre un nombre positif.';
            erreur.style.display = 'block';
            return;
        }

        try {
            if (id) {
                await api('alertes_regle', 'PUT', { id: parseInt(id, 10), body: corps });
            } else {
                await api('alertes_regles', 'POST', { body: corps });
            }
            fermerModale();
            await charger();
        } catch (e) {
            erreur.textContent = e.message;
            erreur.style.display = 'block';
        }
    }

    // -----------------------------------------------------------------
    // Actions sur les lignes
    // -----------------------------------------------------------------

    let cacheRegles = [];

    async function modifierRegle(id) {
        const regle = cacheRegles.find(r => r.id === id);
        if (regle) ouvrirModale(regle);
    }

    async function basculerRegle(id) {
        const regle = cacheRegles.find(r => r.id === id);
        if (!regle) return;
        try {
            await api('alertes_regle', 'PUT', {
                id,
                body: { ...regle, active: !regle.active },
            });
            await charger();
        } catch (e) {
            afficherErreur(e.message);
        }
    }

    async function supprimerRegle(id) {
        if (!window.confirm('Voulez-vous vraiment supprimer cette regle ? Son historique sera egalement efface.')) {
            return;
        }
        try {
            await api('alertes_regle', 'DELETE', { id });
            await charger();
        } catch (e) {
            afficherErreur(e.message);
        }
    }

    function afficherErreur(message) {
        const zone = document.getElementById('finkashi-alertes-erreur');
        zone.textContent = message;
        zone.style.display = 'block';
    }

    // -----------------------------------------------------------------
    // Chargement initial / rafraichissement
    // -----------------------------------------------------------------

    async function charger() {
        const erreur = document.getElementById('finkashi-alertes-erreur');
        erreur.style.display = 'none';

        try {
            const [regles, historique] = await Promise.all([
                api('alertes_regles',     'GET'),
                api('alertes_historique', 'GET'),
            ]);
            cacheRegles = regles;
            rendreRegles(regles);
            rendreHistorique(historique);
        } catch (e) {
            afficherErreur(e.message);
        }
    }

    // -----------------------------------------------------------------
    // Branchement des evenements
    // -----------------------------------------------------------------

    function initialiser() {
        const boutonNouveau = document.getElementById('finkashi-nouvelle-regle');
        if (!boutonNouveau) return;

        boutonNouveau.addEventListener('click', () => ouvrirModale(null));
        document.getElementById('finkashi-modale-enregistrer').addEventListener('click', enregistrerModale);
        document.getElementById('finkashi-modale-annuler').addEventListener('click', fermerModale);
        document.querySelector('.finkashi-modale-fond')?.addEventListener('click', fermerModale);

        // Delegation d'evenements sur le tableau des regles.
        document.getElementById('tableau-regles-corps').addEventListener('click', function (e) {
            const ligne = e.target.closest('tr[data-id]');
            if (!ligne) return;
            const id = parseInt(ligne.dataset.id, 10);

            if (e.target.classList.contains('finkashi-modifier')) {
                modifierRegle(id);
            } else if (e.target.classList.contains('finkashi-basculer')) {
                basculerRegle(id);
            } else if (e.target.classList.contains('finkashi-supprimer')) {
                supprimerRegle(id);
            }
        });

        // Echap ferme la modale.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('finkashi-modale').style.display !== 'none') {
                fermerModale();
            }
        });

        charger();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();
