/**
 * Script de la page Reglages : gere le bouton "Tester la connexion".
 *
 * Charge via wp_enqueue_script (voir MenuAdmin::chargerAssets), avec
 * les donnees serveur (URL AJAX, action, nonce) injectees dans
 * window.finkashiAdmin via wp_localize_script.
 */
(function () {
    'use strict';

    // On attend que le DOM soit pret avant de chercher le bouton.
    function initialiser() {
        const bouton = document.getElementById('finkashi-tester-connexion');
        const sortie = document.getElementById('finkashi-test-resultat');

        if (!bouton || !sortie) {
            // Pas sur la page reglages : on ne fait rien.
            return;
        }

        bouton.addEventListener('click', function (evenement) {
            // Securite : empeche toute soumission de formulaire
            // parente, meme si un autre script s'y attache.
            evenement.preventDefault();
            evenement.stopPropagation();

            testerConnexion(bouton, sortie);
        });
    }

    async function testerConnexion(bouton, sortie) {
        const config = window.finkashiAdmin || {};

        sortie.textContent = 'Test en cours...';
        sortie.className = 'finkashi-test-resultat en-cours';
        bouton.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', config.actionTest);
            formData.append('nonce',  config.nonceTest);

            const reponse = await fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const donnees = await reponse.json();

            if (donnees.success) {
                sortie.textContent = '\u2713 ' + (donnees.data && donnees.data.message || 'Connexion OK.');
                sortie.className = 'finkashi-test-resultat succes';
            } else {
                sortie.textContent = '\u2717 ' + (donnees.data && donnees.data.message || 'Echec du test.');
                sortie.className = 'finkashi-test-resultat echec';
            }
        } catch (erreur) {
            sortie.textContent = '\u2717 Erreur reseau : ' + erreur.message;
            sortie.className = 'finkashi-test-resultat echec';
        } finally {
            bouton.disabled = false;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();
