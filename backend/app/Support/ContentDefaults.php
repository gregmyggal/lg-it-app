<?php

namespace App\Support;

class ContentDefaults
{
    public static function coursDefaults()
    {
        return [
            'section_apropos' => 'Ce cours est conçu pour progresser de manière structurée, avec un accent sur la pratique et la compréhension des concepts fondamentaux. Nos formateurs expérimentés vous guideront à travers chaque étape, en mettant l\'accent sur l\'apprentissage pratique et les projets réels.',
            'section_apprendras' => json_encode([
                'Les concepts fondamentaux et la théorie',
                'Pratique intensive avec des projets réels',
                'Bonnes pratiques et standards de l\'industrie',
                'Accompagnement personnalisé du formateur',
            ]),
            'section_format' => json_encode([
                ['titre' => 'Fréquence', 'contenu' => '1 à 2 sessions par semaine, selon votre niveau'],
                ['titre' => 'Durée', 'contenu' => '60 minutes par session'],
                ['titre' => 'Taille du groupe', 'contenu' => '4-8 élèves maximum pour un accompagnement optimal'],
            ]),
            'section_pourqui' => json_encode([
                'text' => 'Ce cours s\'adresse à tous les niveaux, des débutants complets aux élèves intermédiaires. Aucune expérience préalable n\'est requise.',
                'items' => [
                    'Enfants et adolescents curieux',
                    'Ceux qui veulent apprendre à coder en s\'amusant',
                    'Personnes cherchant une progression structurée',
                ],
            ]),
            'sidebar_pratiques' => json_encode([
                'type' => 'Discovery',
                'niveau' => 'Tous niveaux',
                'langue' => 'Français',
            ]),
            'sidebar_benefits' => json_encode([
                'Formateur expérimenté',
                'Petits groupes',
                'Progression structurée',
                'Projets pratiques réels',
            ]),
        ];
    }

    public static function stageDefaults()
    {
        return [
            'section_apropos' => 'Un stage intensif pendant les vacances scolaires pour progresser rapidement dans un environnement motivant et ludique. Avec des sessions quotidiennes et une approche projet, tu repartiras avec des réalisations concrètes et une progression visible.',
            'section_programme' => json_encode([
                ['jour' => 'Lundi', 'desc' => 'Projet pratique de 8h : création, débogage et présentation'],
                ['jour' => 'Mardi', 'desc' => 'Projet pratique de 10h : création, débogage et présentation'],
                ['jour' => 'Mercredi', 'desc' => 'Projet pratique de 12h : création, débogage et présentation'],
                ['jour' => 'Jeudi', 'desc' => 'Projet pratique de 14h : création, débogage et présentation'],
                ['jour' => 'Vendredi', 'desc' => 'Projet pratique de 16h : création, débogage et présentation'],
            ]),
            'section_strengths' => json_encode([
                'Immersion totale — 5 jours de coding pur',
                'Projets réels — Créez, débuguez, présentez',
                'Ambiance fun — Apprentissage ludique et collaboratif',
                'Progression rapide — Gains visibles en une semaine',
            ]),
            'sidebar_infos' => json_encode([
                'horaires' => '9h00 - 16h30',
            ]),
            'sidebar_inclus' => json_encode([
                'Accès aux outils et ressources',
                'Suivi personnalisé',
                'Certificat de participation',
                'Accès communauté',
            ]),
        ];
    }

    public static function formationDefaults()
    {
        return [
            'section_apropos' => 'Une formation pensée pour répondre aux besoins actuels du marché, avec un accent sur la pratique et l\'application concrète. Nos experts du domaine vous transmettent un savoir-faire directement applicable à votre contexte professionnel.',
            'section_competences' => json_encode([
                'Compétence 1',
                'Compétence 2',
                'Compétence 3',
                'Compétence 4',
            ]),
            'section_approche' => json_encode([
                ['titre' => '📚 Théorie solide', 'desc' => 'Fondamentaux et concepts clés parfaitement assimilés'],
                ['titre' => '💼 Cas pratiques', 'desc' => 'Exercices basés sur des situations réelles'],
                ['titre' => '👥 Accompagnement', 'desc' => 'Suivi personnalisé tout au long du parcours'],
            ]),
            'section_parcours' => json_encode([
                ['phase' => 'Phase 1 : Découverte', 'semaines' => 'Semaines 1-3'],
                ['phase' => 'Phase 2 : Approfondissement', 'semaines' => 'Semaines 4-6'],
                ['phase' => 'Phase 3 : Maîtrise', 'semaines' => 'Semaines 7-9'],
                ['phase' => 'Phase 4 : Intégration', 'semaines' => 'Semaines 10-12'],
            ]),
            'sidebar_infos' => json_encode([
                'format' => 'Flexible (présentiel, en ligne, hybride)',
            ]),
            'sidebar_public' => json_encode([
                'Professionnels en reconversion',
                'Responsables d\'équipe',
                'Chefs de projet',
                'Entreprises',
            ]),
            'sidebar_resultats' => json_encode([
                'Maîtrise complète du sujet',
                'Certificat reconnu',
                'Portfolio de projets',
                'Accès communauté alumni',
            ]),
        ];
    }

    public static function anniversaireDefaults()
    {
        return [
            'section_apropos' => 'Un thème passionnant qui transforme l\'anniversaire en une aventure numérique inoubliable. Les enfants et leurs copains apprendront les bases du code à travers des jeux, défis et créations amusantes. C\'est festif, éducatif, et 100% inoubliable !',
            'section_deroulement' => json_encode([
                ['nom' => '🎮 Accueil & Jeux', 'duree' => '20 min', 'desc' => 'Bienvenue fun et présentation du thème du jour'],
                ['nom' => '💻 Atelier de code', 'duree' => '45 min', 'desc' => 'Créer, jouer, apprendre ensemble à travers des défis ludiques'],
                ['nom' => '🏆 Compétitions amusantes', 'duree' => '30 min', 'desc' => 'Défis, relais de programmation, récompenses pour tous'],
                ['nom' => '🎂 Gâteau & Célébration', 'duree' => '25 min', 'desc' => 'Moment de détente, gâteau, cadeaux surprise'],
            ]),
            'section_pourquoi' => json_encode([
                '100% personnalisé — Le thème et les activités adaptés à l\'âge',
                'Animateurs formés — Équipe expérimentée et bienveillante',
                'Zéro expérience requise — Parfait pour les novices',
                'Souvenir inoubliable — Les enfants parleront longtemps !',
            ]),
            'sidebar_inclus' => json_encode([
                'Animateur formé et expérimenté',
                'Tous les matériels et outils',
                'Gâteau et boissons',
                'Petits cadeaux surprises',
                'Photos souvenirs',
            ]),
        ];
    }
}
