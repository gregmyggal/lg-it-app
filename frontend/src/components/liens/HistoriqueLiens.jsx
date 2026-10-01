import { useState } from 'react';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import { AdminSelect } from '../AdminFormField';
import { Section } from '../ui/Card';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import { restaurerVersion, useHistoriqueLiens } from '../../hooks/useLiens';
import { useToast } from '../../hooks/useToast';
import { getErrorData, getErrorMessage, getStatus } from '../../api/errors';
import { ACTIONS_VERSION, TYPES_LIEN, getStatut, libellePortee } from '../../utils/statuts';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const CHAMPS = { titre: 'Titre', url: 'Adresse', description: 'Description', type: 'Type', seance_numero: 'Portée' };
const PORTEES = [{ value: 'generaux', label: 'Liens généraux' }, ...Array.from({ length: 14 }, (_, i) => ({ value: String(i + 1), label: `Séance ${i + 1}` }))];
const ACTIONS = Object.entries(ACTIONS_VERSION).map(([value, a]) => ({ value, label: a.label }));

const valeur = (champ, v) => {
  if (champ === 'seance_numero') return libellePortee(v);
  if (champ === 'type') return v ? getStatut(TYPES_LIEN, v).label : '—';
  return v === null || v === undefined || v === '' ? '—' : String(v);
};

/** Avant / après lisible : seuls les champs modifiés ; pour un ordre, la liste avant et après de la portée. */
function ResumeVersion({ version }) {
  const { avant, apres, action } = version;
  if (apres && 'liste' in apres) {
    const noms = (l) => l.map((x) => x.titre).join(' › ');
    return (
      <div style={{ fontSize: '13px' }}>
        <div>
          <strong>{libellePortee(apres.seance_numero)}</strong> — avant : {noms(avant.liste)}
        </div>
        <div>après : {noms(apres.liste)}</div>
      </div>
    );
  }
  if (action === 'archivage') return <div style={{ fontSize: '13px' }}>Lien archivé : « {avant?.titre} ».</div>;
  if (action === 'creation') return <div style={{ fontSize: '13px' }}>Lien créé : « {apres?.titre} » ({libellePortee(apres?.seance_numero)}).</div>;
  if (avant?.archive || apres?.archive) return <div style={{ fontSize: '13px' }}>{apres?.archive ? 'Lien de nouveau archivé.' : 'Lien de nouveau visible (archivage annulé).'}</div>;
  const modifies = Object.keys(CHAMPS).filter((c) => (avant?.[c] ?? null) !== (apres?.[c] ?? null));
  return (
    <ul style={{ margin: 0, paddingLeft: '18px', fontSize: '13px' }}>
      {modifies.map((c) => (
        <li key={c}>
          {CHAMPS[c]} : <del>{valeur(c, avant?.[c])}</del> → <strong>{valeur(c, apres?.[c])}</strong>
        </li>
      ))}
    </ul>
  );
}

/**
 * Historique des liens d'un cours (mock-up 02) : versions des 6 derniers mois, filtres portée / action, avant/après et
 * « Restaurer cette version » (professeurs du cours et staff). Une restauration crée elle-même une version ; une version
 * de plus de 6 mois n'est plus proposée ; si le lien a changé depuis, une confirmation explicite est demandée.
 *
 * @param {object} props
 * @param {number|string} props.coursId
 */
export default function HistoriqueLiens({ coursId }) {
  const toast = useToast();
  const [filtres, setFiltres] = useState({ portee: '', action: '' });
  const [page, setPage] = useState(1);
  const historique = useHistoriqueLiens(coursId, filtres, page);
  const [aRestaurer, setARestaurer] = useState(null); // { version, avertissement }
  const [envoi, setEnvoi] = useState(false);

  async function restaurer(confirmer) {
    setEnvoi(true);
    try {
      await restaurerVersion(aRestaurer.version.id, confirmer);
      setARestaurer(null);
      toast.success('Version restaurée. Une nouvelle entrée a été ajoutée à l\'historique ; vous pouvez l\'annuler de la même façon.');
      historique.reload();
    } catch (err) {
      if (getStatus(err) === 409 && getErrorData(err).modifie_depuis) {
        setARestaurer((prev) => ({ ...prev, avertissement: getErrorMessage(err) }));
      } else if (getStatus(err) === 410) {
        setARestaurer(null);
        toast.error('Cette version date de plus de 6 mois : elle n\'est plus restaurable.');
        historique.reload();
      } else {
        setARestaurer(null);
        toast.error(getErrorMessage(err, 'La restauration a échoué.'));
      }
    } finally {
      setEnvoi(false);
    }
  }

  const changer = (champ) => (e) => {
    setFiltres((f) => ({ ...f, [champ]: e.target.value }));
    setPage(1);
  };

  return (
    <>
      <Banner tone="info">
        L'historique conserve 6 mois de modifications. Restaurer ajoute une nouvelle entrée : rien n'est jamais effacé de l'historique avant ce délai.
      </Banner>
      <Section
        title="Historique des liens"
        actions={
          <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            <AdminSelect aria-label="Filtrer par portée" value={filtres.portee} placeholder="Toutes les portées" options={PORTEES} onChange={changer('portee')} />
            <AdminSelect aria-label="Filtrer par action" value={filtres.action} placeholder="Toutes les actions" options={ACTIONS} onChange={changer('action')} />
          </div>
        }
      >
        {historique.loading && <LoadingBlock message="Chargement de l'historique…" lignes={4} />}
        {historique.error && <ErrorBlock message={historique.error} onRetry={historique.reload} />}
        {historique.data && historique.data.data.length === 0 && (
          <EmptyBlock icon="🕘" title="Aucune modification dans les 6 derniers mois">
            Les créations, modifications, archivages, réordonnancements et restaurations de liens apparaîtront ici.
          </EmptyBlock>
        )}
        {historique.data?.data.length > 0 && (
          <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.md }}>
            {historique.data.data.map((v) => (
              <li key={v.id} style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, padding: ADMIN_SPACING.lg }}>
                <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center', marginBottom: ADMIN_SPACING.xs }}>
                  <StatutBadge table={ACTIONS_VERSION} valeur={v.action} />
                  <strong>{v.lien_titre}</strong>
                  <span style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px' }}>
                    {v.auteur?.nom || 'Compte supprimé'} · {new Date(v.created_at).toLocaleString('fr-BE', { dateStyle: 'short', timeStyle: 'short' })}
                  </span>
                </div>
                <ResumeVersion version={v} />
                {v.peut_restaurer && !v.expiree && (
                  <div style={{ marginTop: ADMIN_SPACING.sm }}>
                    <AdminButton size="sm" variant="secondary" onClick={() => setARestaurer({ version: v })}>
                      Restaurer cette version
                    </AdminButton>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
        {historique.data?.meta?.last_page > 1 && (
          <div style={{ display: 'flex', gap: '8px', marginTop: ADMIN_SPACING.lg, alignItems: 'center' }}>
            <AdminButton size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              ‹ Précédent
            </AdminButton>
            <span>
              Page {page} sur {historique.data.meta.last_page}
            </span>
            <AdminButton size="sm" variant="secondary" disabled={page >= historique.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Suivant ›
            </AdminButton>
          </div>
        )}
      </Section>

      {aRestaurer && (
        <AdminModal
          isOpen
          title="Restaurer cette version ?"
          size="sm"
          onClose={() => setARestaurer(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setARestaurer(null)}>
                Annuler
              </AdminButton>
              <AdminButton onClick={() => restaurer(Boolean(aRestaurer.avertissement))} loading={envoi}>
                {aRestaurer.avertissement ? 'Restaurer quand même' : 'Restaurer cette version'}
              </AdminButton>
            </>
          }
        >
          {aRestaurer.avertissement && (
            <Banner tone="warning" role="alert">
              <strong>{aRestaurer.avertissement}</strong>
            </Banner>
          )}
          <p style={{ marginTop: 0 }}>
            L'état précédant cette modification de « {aRestaurer.version.lien_titre} » sera rétabli pour toutes les classes du cours. La version actuelle reste dans l'historique : vous pourrez
            l'annuler de la même façon.
          </p>
        </AdminModal>
      )}
    </>
  );
}
