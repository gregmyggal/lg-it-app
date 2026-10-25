import { matchPath } from 'react-router-dom';

/** Icônes de navigation (SVG en ligne, 24×24) partagées par les onglets, la feuille « Plus » et la barre latérale. */
const ICONES = {
  accueil: <><path d="M3 11 12 4l9 7v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></>,
  classes: <><path d="M3 9 12 4l9 5-9 5z" /><path d="M7 11.5V16c3 2 7 2 10 0v-4.5" /></>,
  mois: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></>,
  liens: <><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1" /><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" /></>,
  moi: <><circle cx="12" cy="8" r="4" /><path d="M4 21c1-4 4-6 8-6s7 2 8 6" /></>,
  timesheets: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  professeurs: <><circle cx="9" cy="8" r="3.5" /><path d="M2 20c.6-4 3-6 7-6s6.4 2 7 6M17 5a3.5 3.5 0 0 1 0 7M19 14c1.5.8 2.5 2.6 3 5" /></>,
  plus: <><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></>,
};

export function NavIcon({ nom }) {
  return (
    <svg className="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      {ICONES[nom]}
    </svg>
  );
}

/**
 * Configuration unique de la navigation du portail (UI-M2) : onglets du smartphone, feuille « Plus / Moi » et barre latérale
 * de l'ordinateur en dérivent. `principal` = entrées toujours visibles ; `groupes` = le reste, rangé par thème.
 *
 * @param {{role: string}} user
 * @returns {{principal: object[], groupes: {label: string, items: object[]}[], dernierOnglet: string}}
 */
export function navigationPour(user) {
  const staff = user?.role === 'admin' || user?.role === 'directeur';

  if (!staff) {
    return {
      principal: [
        { to: '/aujourdhui', label: "Aujourd'hui", icone: 'accueil' },
        { to: '/timesheets', label: 'Mon mois', icone: 'mois' },
        { to: '/mes-classes', label: 'Classes', icone: 'classes' },
      ],
      groupes: [{ label: 'Ressources', items: [{ to: '/mes-ressources', label: 'Liens de mes cours' }] }],
      dernierOnglet: 'Moi',
    };
  }

  return {
    principal: [
      { to: '/aujourdhui', label: "Aujourd'hui", icone: 'accueil' },
      { to: '/admin/timesheets', label: 'Timesheets', icone: 'timesheets', end: true },
      { to: '/admin/classes', label: 'Classes', icone: 'classes' },
      { to: '/admin/professeurs', label: 'Professeurs', icone: 'professeurs' },
    ],
    groupes: [
      {
        label: 'Opérationnel',
        items: [
          { to: '/admin/timesheets/parametres', label: 'Paramètres timesheets' },
          { to: '/admin/employeurs', label: 'Entités employeurs' },
          ...(user.role === 'admin' ? [{ to: '/admin/staff', label: 'Équipe' }] : []),
        ],
      },
      {
        label: 'Scolarité',
        items: [
          { to: '/admin/calendrier', label: 'Calendrier' },
          { to: '/admin/annees-scolaires', label: 'Années scolaires' },
          { to: '/admin/calendrier-scolaire', label: 'Calendrier scolaire' },
        ],
      },
      {
        label: 'Contenu',
        items: [
          { to: '/admin/cours', label: 'Cours' },
          { to: '/admin/stages', label: 'Stages' },
          { to: '/admin/formations', label: 'Formations' },
          { to: '/admin/anniversaires', label: 'Anniversaires' },
          { to: '/admin/types-formation', label: 'Types de formation' },
        ],
      },
    ],
    dernierOnglet: 'Plus',
  };
}

/** Vrai si `pathname` relève de cette entrée (ex. `/admin/classes/3` pour `/admin/classes`). */
export function estActive(entree, pathname) {
  return !!matchPath({ path: entree.to, end: !!entree.end }, pathname);
}

/**
 * L'entrée active parmi `principal` : la plus spécifique l'emporte (`/admin/timesheets/parametres` n'active pas « Timesheets »,
 * qui est `end`). Retourne null si la page relève du « Plus ».
 */
export function ongletActif(nav, pathname) {
  return nav.principal.find((e) => estActive(e, pathname)) || null;
}
