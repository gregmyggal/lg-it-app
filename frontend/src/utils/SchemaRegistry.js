// Schema definition for each content section
// Defines structure, validation rules, and rendering hints

export const SCHEMA_REGISTRY = {
  // ============ COURS ============
  section_apprendras: {
    type: 'list',
    itemType: 'string',
    label: 'Ce que tu apprendras',
    placeholder: 'Ex: Créer des animations',
    icon: '✨',
    validation: {
      minLength: 5,
      maxLength: 200,
    },
  },
  section_format: {
    type: 'list-of-objects',
    itemType: { titre: 'string', contenu: 'string' },
    label: 'Format & horaires',
    icon: '🕐',
    fields: [
      { key: 'titre', label: 'Titre', placeholder: 'Ex: Fréquence', type: 'text' },
      { key: 'contenu', label: 'Contenu', placeholder: 'Ex: 1-2 sessions/semaine', type: 'textarea' },
    ],
    validation: {
      minItems: 1,
      maxItems: 10,
    },
  },
  section_pourqui: {
    type: 'object-with-list',
    label: 'Pour qui ?',
    icon: '🎯',
    fields: [
      { key: 'text', label: 'Description', type: 'textarea', maxLength: 300 },
      {
        key: 'items',
        label: 'Catégories',
        type: 'list',
        itemType: 'string',
        placeholder: 'Ex: Débutants',
      },
    ],
  },
  sidebar_benefits: {
    type: 'list',
    itemType: 'string',
    label: 'Pourquoi ce cours ?',
    icon: '💪',
    placeholder: 'Ex: Petit groupe',
    validation: { minLength: 5, maxLength: 150 },
  },

  // ============ FORMATIONS ============
  section_competences: {
    type: 'list',
    itemType: 'string',
    label: 'Compétences',
    icon: '🎯',
    placeholder: 'Ex: Algorithmique avancée',
    validation: { minLength: 5, maxLength: 200 },
  },
  section_approche: {
    type: 'list-of-objects',
    itemType: { titre: 'string', desc: 'string' },
    label: 'Notre approche',
    icon: '📚',
    fields: [
      { key: 'titre', label: 'Titre', placeholder: 'Ex: 📚 Théorie solide', type: 'text' },
      { key: 'desc', label: 'Description', placeholder: 'Ex: Fondamentaux...', type: 'textarea' },
    ],
  },
  section_parcours: {
    type: 'list-of-objects',
    itemType: { phase: 'string', semaines: 'string' },
    label: 'Votre parcours',
    icon: '🛤️',
    fields: [
      { key: 'phase', label: 'Phase', placeholder: 'Ex: Phase 1 : Découverte', type: 'text' },
      { key: 'semaines', label: 'Durée', placeholder: 'Ex: Semaines 1-3', type: 'text' },
    ],
  },
  sidebar_infos: {
    type: 'object',
    label: 'Infos clés',
    icon: 'ℹ️',
    fields: [
      { key: 'type', label: 'Type', type: 'text', placeholder: 'Ex: Formation complète' },
      { key: 'duree', label: 'Durée', type: 'text', placeholder: 'Ex: 6 mois' },
      { key: 'format', label: 'Format', type: 'text', placeholder: 'Ex: En ligne + présentiel' },
    ],
  },
  sidebar_public: {
    type: 'list',
    itemType: 'string',
    label: 'Pour qui ?',
    icon: '👥',
    placeholder: 'Ex: Développeurs Python',
    validation: { minLength: 5, maxLength: 150 },
  },
  sidebar_resultats: {
    type: 'list',
    itemType: 'string',
    label: 'Résultats',
    icon: '✨',
    placeholder: 'Ex: Certifiés en web dev',
    validation: { minLength: 5, maxLength: 150 },
  },

  // ============ STAGES ============
  section_programme: {
    type: 'list-of-objects',
    itemType: { jour: 'string', desc: 'string' },
    label: 'Programme',
    icon: '📅',
    fields: [
      { key: 'jour', label: 'Jour', placeholder: 'Ex: Lundi', type: 'text' },
      { key: 'desc', label: 'Activités', placeholder: 'Ex: Projet 8h : création, débogage...', type: 'textarea' },
    ],
  },
  section_strengths: {
    type: 'list',
    itemType: 'string',
    label: 'Points forts',
    icon: '⚡',
    placeholder: 'Ex: Apprentissage par projet',
    validation: { minLength: 5, maxLength: 150 },
  },
  sidebar_inclus: {
    type: 'list',
    itemType: 'string',
    label: 'Inclus',
    icon: '✓',
    placeholder: 'Ex: Repas fourni',
    validation: { minLength: 5, maxLength: 150 },
  },

  // ============ ANNIVERSAIRES ============
  section_deroulement: {
    type: 'list-of-objects',
    itemType: { nom: 'string', duree: 'string', desc: 'string' },
    label: 'Déroulement',
    icon: '🎮',
    fields: [
      { key: 'nom', label: 'Activité', placeholder: 'Ex: 🎮 Accueil & Jeux', type: 'text' },
      { key: 'duree', label: 'Durée', placeholder: 'Ex: 20 min', type: 'text' },
      { key: 'desc', label: 'Description', placeholder: 'Ex: Bienvenue fun...', type: 'textarea' },
    ],
  },
  section_pourquoi: {
    type: 'list',
    itemType: 'string',
    label: 'Pourquoi ?',
    icon: '💡',
    placeholder: 'Ex: Fête mémorable',
    validation: { minLength: 5, maxLength: 150 },
  },
  sidebar_tarification: {
    type: 'object',
    label: 'Tarification',
    icon: '💰',
    fields: [
      { key: 'tranche_age', label: 'Tranche d\'âge', type: 'text', placeholder: 'Ex: 6-10 ans' },
      { key: 'tarif', label: 'Tarif', type: 'text', placeholder: 'Ex: 25€/enfant' },
      { key: 'duree', label: 'Durée', type: 'text', placeholder: 'Ex: 2 heures' },
    ],
  },
  sidebar_options: {
    type: 'string',
    label: 'Options',
    icon: '🎁',
    placeholder: 'Décrivez les options disponibles...',
  },
};

export function getSchema(sectionKey) {
  return SCHEMA_REGISTRY[sectionKey] || null;
}

export function validateValue(value, schema) {
  if (!schema) return { valid: true };

  // String validation
  if (schema.type === 'string') {
    if (!value || typeof value !== 'string') return { valid: false, error: 'Texte requis' };
    if (schema.validation?.minLength && value.length < schema.validation.minLength) {
      return { valid: false, error: `Minimum ${schema.validation.minLength} caractères` };
    }
    if (schema.validation?.maxLength && value.length > schema.validation.maxLength) {
      return { valid: false, error: `Maximum ${schema.validation.maxLength} caractères` };
    }
    return { valid: true };
  }

  // List validation
  if (schema.type === 'list' || schema.type === 'list-of-objects') {
    if (!Array.isArray(value)) return { valid: false, error: 'Doit être une liste' };
    if (schema.validation?.minItems && value.length < schema.validation.minItems) {
      return { valid: false, error: `Minimum ${schema.validation.minItems} élément(s)` };
    }
    if (schema.validation?.maxItems && value.length > schema.validation.maxItems) {
      return { valid: false, error: `Maximum ${schema.validation.maxItems} éléments` };
    }
    return { valid: true };
  }

  // Object validation
  if (schema.type === 'object' || schema.type === 'object-with-list') {
    if (!value || typeof value !== 'object') return { valid: false, error: 'Objet requis' };
    return { valid: true };
  }

  return { valid: true };
}
