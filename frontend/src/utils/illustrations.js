// Correspondance emoji → illustration d'état vide (UI-M8).
/** Emoji historiquement passés à `EmptyBlock` → nom du dessin de `Illustration`. */
const PAR_EMOJI = {
  '✅': 'ok',
  '🏫': 'classes',
  '👥': 'personnes',
  '👨‍🏫': 'personnes',
  '👩‍🏫': 'personnes',
  '📅': 'calendrier',
  '📆': 'calendrier',
  '🗓️': 'calendrier',
  '📚': 'livre',
  '📭': 'boite',
  '🔎': 'recherche',
  '🔗': 'lien',
  '🕘': 'horloge',
};

export function nomIllustration(emoji) {
  return PAR_EMOJI[emoji] || null;
}

