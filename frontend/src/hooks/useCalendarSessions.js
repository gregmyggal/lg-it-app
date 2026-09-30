import client from '../api/client';
import { useApiQuery } from './useApiQuery';

/**
 * Sessions du calendrier pour une vue donnée.
 * @param {'mois'|'semaine'|'agenda'} vue
 * @param {object} periode { annee, mois } | { annee, semaine } | { du, au }
 * @param {{annee_scolaire_id?: string, classe_id?: string, cours_id?: string}} filtres
 * @returns {ReturnType<typeof useApiQuery>} data = { sessions: [...], parJour: { 'YYYY-MM-DD': [...] } }
 */
export function useCalendarSessions(vue, periode, filtres) {
  const filtresUtiles = Object.fromEntries(Object.entries(filtres).filter(([, v]) => v));

  function charger() {
    if (vue === 'mois') {
      return client
        .get('/calendar/month', { params: { year: periode.annee, month: periode.mois, ...filtresUtiles } })
        .then((res) => normaliser(res.data.data, true));
    }
    if (vue === 'semaine') {
      return client
        .get('/calendar/week', { params: { year: periode.annee, week: periode.semaine, ...filtresUtiles } })
        .then((res) => normaliser(res.data.data, false));
    }
    return client
      .get('/calendar/agenda', { params: { from_date: periode.du, to_date: periode.au, ...filtresUtiles } })
      .then((res) => normaliser(res.data.data, false));
  }

  return useApiQuery(charger, [vue, JSON.stringify(periode), JSON.stringify(filtresUtiles)]);
}

function normaliser(data, estGroupe) {
  const sessions = estGroupe ? Object.values(data || {}).flat() : data || [];
  const parJour = {};
  sessions.forEach((s) => {
    (parJour[s.date] ||= []).push(s);
  });
  return { sessions, parJour };
}
