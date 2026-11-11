import { useState } from 'react';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import { creerPartage, lienPartage, regenererPartage, revoquerPartage, usePartages } from '../../hooks/useDepot';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import { STATUTS_PARTAGE } from '../../utils/statuts';
import { formatDate, formatDateHeure } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

async function copier(texte) {
  try {
    await navigator.clipboard.writeText(texte);
    return true;
  } catch {
    return false;
  }
}

/** Zone du lien : sélectionnable (repli si le presse-papiers est refusé) avec bouton « Copier le lien ». */
function LienCopiable({ lien, expireLe }) {
  const toast = useToast();
  return (
    <div style={{ display: 'grid', gap: ADMIN_SPACING.sm }}>
      <input
        readOnly
        aria-label="Lien à transmettre au parent"
        value={lien}
        onFocus={(e) => e.target.select()}
        style={{ width: '100%', boxSizing: 'border-box', padding: ADMIN_SPACING.sm, minHeight: '44px' }}
      />
      <div>
        <AdminButton
          onClick={async () => (await copier(lien)) ? toast.success('Lien copié.') : toast.warning('Copie impossible : sélectionnez le lien et copiez-le à la main.')}
        >
          Copier le lien
        </AdminButton>
      </div>
      <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>
        Valable jusqu'au {formatDateHeure(expireLe)}. Toute personne qui possède ce lien peut télécharger ces fichiers : transmettez-le uniquement au parent concerné.
      </div>
    </div>
  );
}

/** Mock-up 02 : créer le lien d'une sélection de fichiers (rien n'est envoyé à cette étape). */
export function PartagerModal({ classeId, fichiers, onClose }) {
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [resultat, setResultat] = useState(null);
  const libelles = [...new Set(fichiers.map((f) => f.libelle || 'Sans libellé'))];

  async function creer() {
    setEnCours(true);
    setErreur(null);
    try {
      setResultat(await creerPartage(classeId, fichiers.map((f) => f.id)));
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnCours(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title="Partager à un parent"
      onClose={onClose}
      closeOnBackdrop={!enCours}
      footer={
        resultat ? (
          <AdminButton variant="secondary" onClick={onClose}>Terminer</AdminButton>
        ) : (
          <>
            <AdminButton variant="secondary" onClick={onClose} disabled={enCours}>Annuler</AdminButton>
            <AdminButton onClick={creer} loading={enCours}>
              Créer le lien ({fichiers.length} fichier{fichiers.length > 1 ? 's' : ''})
            </AdminButton>
          </>
        )
      }
    >
      {!resultat && (
        <div style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
          {libelles.length > 1 && (
            <Banner tone="warning">
              Cette sélection mélange plusieurs libellés ({libelles.join(', ')}). Vérifiez que tous les fichiers concernent le même enfant.
            </Banner>
          )}
          <ul style={{ margin: 0, paddingLeft: '18px', fontSize: '14px' }}>
            {fichiers.map((f) => (
              <li key={f.id} style={{ overflowWrap: 'anywhere' }}>
                {f.nom} <span style={{ color: ADMIN_COLORS.textSecondary }}>· {f.libelle || 'Sans libellé'}</span>
              </li>
            ))}
          </ul>
          <p style={{ margin: 0, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
            Le lien est valable 7 jours. Il ne donne accès qu'à ces fichiers. Rien n'est envoyé à cette étape.
          </p>
          {erreur && <Banner tone="error">{erreur}</Banner>}
        </div>
      )}
      {resultat && (
        <div style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
          <Banner tone="success">Lien créé. Rien n'a encore été envoyé.</Banner>
          <LienCopiable lien={resultat.lien} expireLe={resultat.data.expire_le} />
        </div>
      )}
    </AdminModal>
  );
}

/** Mock-up 03 : liens parents du stage, avec statut, copie, régénération et révocation. */
export function PartagesTab({ classeId, recharge }) {
  const partages = usePartages(classeId);
  const toast = useToast();
  const [action, setAction] = useState(null); // { type: 'regenerer' | 'revoquer', partage }
  const [enCours, setEnCours] = useState(false);
  const [regenere, setRegenere] = useState(null); // { lien, data } affiché après régénération

  async function copierLien(p) {
    try {
      const lien = await lienPartage(classeId, p.id);
      if (await copier(lien)) toast.success('Lien copié.');
      else toast.warning('Copie impossible. Régénérez le lien pour l\'afficher.');
    } catch (err) {
      toast.error(getErrorMessage(err));
    }
  }

  async function confirmer() {
    setEnCours(true);
    try {
      if (action.type === 'revoquer') {
        await revoquerPartage(classeId, action.partage.id);
        toast.success('Lien révoqué.');
      } else {
        setRegenere(await regenererPartage(classeId, action.partage.id));
      }
      setAction(null);
      partages.reload();
      recharge?.();
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setEnCours(false);
    }
  }

  const liste = partages.data || [];

  return (
    <div>
      {partages.loading && <LoadingBlock message="Chargement des liens…" lignes={3} />}
      {partages.error && <ErrorBlock message={partages.error} onRetry={partages.reload} />}
      {partages.data && liste.length === 0 && (
        <EmptyBlock icon="🔗" title="Aucun lien pour l'instant">
          Dans l'onglet Fichiers, sélectionnez les fichiers d'un enfant puis « Partager à un parent » pour créer un lien de 7 jours.
        </EmptyBlock>
      )}
      <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.sm }}>
        {liste.map((p) => (
          <li
            key={p.id}
            style={{
              border: `1px solid ${ADMIN_COLORS.border}`,
              borderRadius: ADMIN_RADIUS.md,
              padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
              background: ADMIN_COLORS.cardBg,
              display: 'grid',
              gap: ADMIN_SPACING.xs,
            }}
          >
            <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center' }}>
              <strong>{p.libelle || 'Sélection mixte'}</strong>
              <StatutBadge table={STATUTS_PARTAGE} valeur={p.statut} />
              <span style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
                {p.nb_fichiers} fichier{p.nb_fichiers > 1 ? 's' : ''}
              </span>
            </div>
            <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>
              {p.statut === 'actif' && `Expire le ${formatDateHeure(p.expire_le)}`}
              {p.statut === 'expire' && `Expiré le ${formatDate(p.expire_le)}`}
              {p.statut === 'revoque' && `Révoqué le ${formatDate(p.revoque_le)}`}
              {` · créé par ${p.cree_par || '—'} · ouvert ${p.nb_ouvertures} fois`}
              {p.derniere_ouverture_le ? ` (dernière : ${formatDateHeure(p.derniere_ouverture_le)})` : ''}
            </div>
            <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', marginTop: ADMIN_SPACING.xs }}>
              {p.statut === 'actif' && (
                <AdminButton size="sm" onClick={() => copierLien(p)}>Copier le lien</AdminButton>
              )}
              <AdminButton size="sm" variant="secondary" onClick={() => setAction({ type: 'regenerer', partage: p })}>
                Régénérer le lien
              </AdminButton>
              {p.statut === 'actif' && (
                <AdminButton size="sm" variant="secondary" onClick={() => setAction({ type: 'revoquer', partage: p })}>
                  Révoquer le lien
                </AdminButton>
              )}
            </div>
          </li>
        ))}
      </ul>

      <AdminModal
        isOpen={Boolean(action)}
        title={action?.type === 'revoquer' ? 'Révoquer ce lien ?' : 'Régénérer ce lien ?'}
        size="sm"
        onClose={() => setAction(null)}
        footer={
          <>
            <AdminButton variant="secondary" onClick={() => setAction(null)}>Annuler</AdminButton>
            <AdminButton variant={action?.type === 'revoquer' ? 'danger' : 'primary'} onClick={confirmer} loading={enCours}>
              {action?.type === 'revoquer' ? 'Révoquer le lien' : 'Régénérer le lien'}
            </AdminButton>
          </>
        }
      >
        <p style={{ margin: 0 }}>
          {action?.type === 'revoquer'
            ? 'Le lien cessera de fonctionner immédiatement : le parent verra « lien expiré ». Les fichiers restent dans le stage.'
            : 'Un nouveau lien valable 7 jours sera créé avec les mêmes fichiers. L\'ancien lien ne fonctionnera plus. Le nouveau lien ne sera pas envoyé automatiquement.'}
        </p>
      </AdminModal>

      <AdminModal
        isOpen={Boolean(regenere)}
        title="Nouveau lien créé"
        onClose={() => setRegenere(null)}
        footer={<AdminButton variant="secondary" onClick={() => setRegenere(null)}>Terminer</AdminButton>}
      >
        {regenere && <LienCopiable lien={regenere.lien} expireLe={regenere.data.expire_le} />}
      </AdminModal>
    </div>
  );
}
