/** Page d'arrivée du portail selon le rôle : « Aujourd'hui » pour le professeur (UI-M3), les classes pour la direction. */
export function accueilPour(role) {
  return role === 'professeur' ? '/aujourdhui' : '/admin/classes';
}
