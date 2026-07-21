<?php

namespace App\Support;

/**
 * Marketing Examples for Content Sections
 * Professional, engaging content tailored to each course type
 */
class MarketingExamples
{
    public static function getScratchJuniorExample()
    {
        return [
            'section_apropos' => 'Découvrez la programmation de manière ludique et créative ! Scratch Junior est la porte d\'entrée parfaite pour les enfants qui souhaitent apprendre les bases de la programmation en créant leurs propres jeux et animations interactives.',

            'section_apprendras' => [
                'Créer des animations et des jeux interactifs',
                'Maîtriser les blocs de programmation (boucles, conditions, variables)',
                'Développer votre logique et votre pensée algorithmique',
                'Collaborer avec d\'autres créateurs et partager vos projets',
                'Résoudre des problèmes de manière créative',
                'Comprendre les principes fondamentaux du code',
            ],

            'section_format' => [
                ['titre' => 'Fréquence', 'contenu' => '1-2 sessions par semaine'],
                ['titre' => 'Durée par session', 'contenu' => '1h30 d\'apprentissage actif'],
                ['titre' => 'Format', 'contenu' => 'En ligne + pratique collaborative'],
                ['titre' => 'Groupe', 'contenu' => 'Petit groupe (max 8 enfants)'],
            ],

            'section_pourqui' => [
                'text' => 'Ce cours est conçu pour tous les niveaux, des débutants complets aux enfants ayant déjà une première expérience avec la programmation.',
                'items' => [
                    'Enfants curieux (6-8 ans)',
                    'Enfants ayant des aptitudes en logique',
                    'Jeunes créatifs souhaitant exprimer leurs idées',
                    'Tous ceux fascinés par la technologie',
                ],
            ],

            'sidebar_pratiques' => [
                'niveau' => 'Débutant',
                'prerequis' => 'Aucun',
                'materiel' => 'Ordinateur + connexion internet',
            ],

            'sidebar_benefits' => [
                'Formateurs expérimentés passionnés par la pédagogie',
                'Petit groupe pour une attention personnalisée',
                'Projets concrets et motivants',
                'Certificat de participation',
                'Accès à la communauté Scratch mondiale',
                'Portfolio numérique de vos créations',
            ],
        ];
    }

    public static function getPythonAdoExample()
    {
        return [
            'section_apropos' => 'Python est le langage de programmation le plus demandé en 2024. Idéal pour débuter en vrai code, Python allie simplicité et puissance. Apprenez à programmer en créant des applications réelles et amusantes.',

            'section_apprendras' => [
                'Maîtriser la syntaxe Python et les structures de base',
                'Créer des applications console interactives',
                'Utiliser les bibliothèques populaires (NumPy, Pandas)',
                'Développer des jeux simples avec Pygame',
                'Manipuler des données et créer des visualisations',
                'Déboguer efficacement votre code',
                'Collaborer avec Git et GitHub',
            ],

            'section_format' => [
                ['titre' => 'Rythme', 'contenu' => '2-3 sessions par semaine'],
                ['titre' => 'Durée', 'contenu' => '2 heures par session'],
                ['titre' => 'Approche', 'contenu' => 'Théorie + pratique project-based'],
                ['titre' => 'Accès', 'contenu' => 'Présentiel ou visioconférence'],
            ],

            'section_pourqui' => [
                'text' => 'Pour ados et pré-ados ayant une première expérience avec la programmation (Scratch ou équivalent) et désireux de progresser vers un vrai langage.',
                'items' => [
                    'Ados passionnés par le code (13+)',
                    'Étudiants préparant des examens informatique',
                    'Jeunes envisageant une carrière tech',
                    'Créatifs souhaitant développer des jeux',
                ],
            ],

            'sidebar_pratiques' => [
                'niveau' => 'Intermédiaire',
                'prerequis' => 'Notions de programmation (Scratch requis)',
                'materiel' => 'Ordinateur puissant + IDE gratuit',
            ],

            'sidebar_benefits' => [
                'Instructeurs certifiés avec 10+ ans d\'expérience',
                'Challenges hebdomadaires et projets stimulants',
                'Partage de ressources actualisées',
                'Coaching personnalisé',
                'Portfolio GitHub avec vrais projets',
                'Recommandations pour stages/jobs',
            ],
        ];
    }

    public static function getWebDevFormationExample()
    {
        return [
            'section_apropos' => 'Formation complète au développement web moderne. Maîtrisez HTML, CSS, JavaScript et les frameworks frontend populaires. Devenez un développeur web compétent capable de créer des sites et applications web professionnels.',

            'section_competences' => [
                'HTML5 sémantique et responsive design',
                'CSS3 avancé (Flexbox, Grid, animations)',
                'JavaScript ES6+ et DOM manipulation',
                'React ou Vue.js (frameworks modernes)',
                'Backend avec Node.js et Express',
                'Bases de données (SQL + MongoDB)',
                'Déploiement et DevOps essentiels',
            ],

            'section_approche' => [
                ['titre' => '📚 Théorie solide', 'desc' => 'Fondamentaux parfaitement assimilés'],
                ['titre' => '🚀 Pratique intensive', 'desc' => 'Projets réels et défis progressifs'],
                ['titre' => '🤝 Mentorat', 'desc' => 'Support individuel de pros du secteur'],
                ['titre' => '💼 Portfolio', 'desc' => 'Projets professionnels pour vos CV'],
            ],

            'section_parcours' => [
                ['phase' => 'Phase 1 : Fondations Web', 'semaines' => 'Semaines 1-4'],
                ['phase' => 'Phase 2 : Frontend Moderne', 'semaines' => 'Semaines 5-10'],
                ['phase' => 'Phase 3 : Backend & Databases', 'semaines' => 'Semaines 11-15'],
                ['phase' => 'Phase 4 : Projet Capstone', 'semaines' => 'Semaines 16-20'],
            ],

            'sidebar_infos' => [
                'type' => 'Formation intensive',
                'duree' => '5 mois (20 semaines)',
                'format' => 'En ligne + sessions live',
            ],

            'sidebar_public' => [
                'Reconversion professionnelle',
                'Étudiants en informatique',
                'Freelancers souhaitant monter en compétences',
                'Passionnés de technologie sans expérience préalable',
            ],

            'sidebar_resultats' => [
                'Capable de créer des sites web complets',
                'Prêt pour un poste Junior Developer',
                '6+ projets professionnels en portfolio',
                'Compétences en demande sur le marché',
            ],
        ];
    }

    public static function getSummerStageExample()
    {
        return [
            'section_apropos' => 'Stage d\'été intensif : 1 semaine de programmation immersive ! Un vrai défi créatif où tu programmeras tous les jours pour créer ton propre jeu ou application. Parfait pour progresser rapidement et rencontrer d\'autres jeunes codeurs.',

            'section_programme' => [
                ['jour' => 'Lundi', 'desc' => 'Initiation + présentation du défi. Configuration des outils. Brainstorming du projet personnel.'],
                ['jour' => 'Mardi', 'desc' => 'Atelier technique 1 : Programmation avancée. Travail en équipe sur mini-projets.'],
                ['jour' => 'Mercredi', 'desc' => 'Atelier technique 2 : Design et UX. Itération sur vos projets personnels.'],
                ['jour' => 'Jeudi', 'desc' => 'Présentations mi-semaine + démos. Feedback des mentors et ajustements.'],
                ['jour' => 'Vendredi', 'desc' => 'Finalisations et présentations publiques. Célébration + certificats.'],
            ],

            'section_strengths' => [
                'Immersion totale : 35h de programmation en 5 jours',
                'Mentors experts disponibles en permanence',
                'Projets réels (pas d\'exercices théoriques)',
                'Partage et collaboration avec d\'autres jeunes',
                'Ambiance créative et ludique',
                'Certificat et portfolio à présenter',
            ],

            'sidebar_infos' => [
                'dates' => 'Juillet 15-19 ou Août 5-9',
                'tarif' => '299€ (ou 199€ avec réduction early-bird)',
                'horaires' => '9h-17h (pause midi incluse)',
            ],

            'sidebar_inclus' => [
                'Accès à tous les outils professionnels',
                'Repas et snacks fournis',
                'Mentoring en petit groupe',
                'Certificat de participation',
                'Accès permanent aux ressources',
            ],
        ];
    }

    public static function getBirthdayEventExample()
    {
        return [
            'section_apropos' => 'Anniversaire Tech inoubliable ! Ton thème préféré (Jeux vidéo, Robotique, Créatif) + programmation ludique. Une expérience unique où toi et tes amis créerez ensemble un mini-jeu ou une animation personnalisée.',

            'section_deroulement' => [
                ['nom' => '🎮 Accueil & Jeux', 'duree' => '20 min', 'desc' => 'Bienvenue festive, présentation du défi du jour'],
                ['nom' => '💻 Créativité Libre', 'duree' => '50 min', 'desc' => 'Programmer ensemble votre jeu ou animation (Scratch, Micro:bit)'],
                ['nom' => '🎁 Challenge & Jeux', 'duree' => '40 min', 'desc' => 'Défis ludiques, mini-compétitions de programmation'],
                ['nom' => '🎉 Gâteau & Célébration', 'duree' => '30 min', 'desc' => 'Partage du gâteau, remise des diplômes "Mini Dev"'],
            ],

            'section_pourquoi' => [
                'Une vraie expérience créative, pas juste du divertissement',
                'Souvenir inoubliable avec vos amis',
                'Découverte de la programmation en s\'amusant',
                'Certificat "Mini Developer" personnalisé',
                'Photos et vidéos à ramener chez vous',
            ],

            'sidebar_tarification' => [
                'tranche_age' => '6-10 ans',
                'tarif' => '25€ par enfant',
                'duree' => '2h30',
            ],

            'sidebar_inclus' => [
                'Tous les matériels fournis',
                'Encadrement de 2 mentors',
                'Certificat "Mini Dev" personnalisé',
                'Photo de groupe',
            ],

            'sidebar_options' => 'Options possibles : Extension 30min supplémentaires (+10€), Gâteau premium (+15€), Pack photos HD (+5€). Groupe min 4 enfants, max 12. Réservation 1 semaine avant.',
        ];
    }
}
