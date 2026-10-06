import { useCallback, useEffect, useState } from 'react';
import client from '../../api/client';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import Banner from '../ui/Banner';
import { AdminCheckbox, AdminFormField, AdminInput, AdminTextarea } from '../AdminFormField';
import { STATUTS_TIMESHEET } from '../../utils/statuts';
import { formatHeures } from '../../utils/format';
import { formatDateCourte, MOIS_LONGS } from '../../utils/dates';
import { getErrorData, getErrorMessage, getFieldErrors, getStatus } from '../../api/errors';
import { ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const MOTIF_MIN = 10;
const APERCU_SEANCES = 6;

function pluriel(n, singulier, plurielTexte = `${singulier}s`) {
  return `${n} ${n > 1 ? plurielTexte : singulier}`;
}

const nomComplet = (p) => `${p.prenom} ${p.nom}`;

/** Charge un impact calculé par le serveur ; `recharger` pour l'état d'erreur. */
function useImpact(url) {
  const [impact, setImpact] = useState(null);
  const [erreur, setErreur] = useState(null);
  const charger = useCallback(() => {
    setErreur(null);
    setImpact(null);
    client.get(url)
      .then((res) => setImpact(res.data.data))
      .catch((err) => setErreur(getErrorMessage(err)));
  }, [url]);
  useEffect(charger, [charger]);
  return { impact, erreur, recharger: charger };
}

function Chargement({ texte }) {
  return <p aria-live="polite" style={{ margin: 0, color: 'var(--c-text-2)' }}>{texte}</p>;
}

function ErreurChargement({ texte, onReessayer }) {
  return (
    <Banner tone="error" actions={<AdminButton size="sm" variant="secondary" onClick={onReessayer}>Réessayer</AdminButton>}>
      {texte}
    </Banner>
  );
}

/**
 * PROF-02 : ce que le nettoyage des séances à venir changera (archivage et suppression, même règle serveur).
 * Les avertissements sont non bloquants.
 */
function ImpactSeances({ impact, pronom }) {
  const d = impact.detail_seances;
  const rien = impact.classes_actives === 0 && impact.seances_a_venir === 0;
  if (rien) {
    return <p style={{ margin: 0, color: 'var(--c-text-2)' }}>Aucune classe ni séance à venir à son nom.</p>;
  }
  const details = [
    d.classe > 0 && `${d.classe} de ses classes`,
    d.ajout > 0 && pluriel(d.ajout, 'ajout ponctuel', 'ajouts ponctuels'),
    d.remplacement > 0 && pluriel(d.remplacement, 'remplacement assuré', 'remplacements assurés'),
    d.deja_remplace > 0 && `${pluriel(d.deja_remplace, 'séance')} où ${pronom} était déjà remplacé(e) (le remplaçant reste assigné)`,
  ].filter(Boolean);
  return (
    <>
      <h3 style={{ margin: 0, fontSize: '13px', textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--c-text-2)' }}>Ce qui sera nettoyé</h3>
      <ul style={{ margin: 0, paddingLeft: ADMIN_SPACING.lg }}>
        {impact.classes_actives > 0 && (
          <li>
            <strong>{pluriel(impact.classes_actives, 'classe')}</strong> : son assignation se termine aujourd&apos;hui
            ({impact.classes.map((c) => c.nom).join(', ')})
          </li>
        )}
        {impact.seances_a_venir > 0 && (
          <li>
            <strong>{pluriel(impact.seances_a_venir, 'séance à venir', 'séances à venir')}</strong> ne lui seront plus assignées
            {details.length > 1 && (
              <details>
                <summary style={{ cursor: 'pointer', fontSize: '13px', color: 'var(--c-text-2)' }}>Détail par type</summary>
                <ul style={{ margin: 0, paddingLeft: ADMIN_SPACING.lg }}>
                  {details.map((t) => <li key={t}>{t}</li>)}
                </ul>
              </details>
            )}
          </li>
        )}
      </ul>
      {impact.seances_sans_professeur > 0 && (
        <Banner tone="warning">
          <strong>{pluriel(impact.seances_sans_professeur, 'séance n’aura', 'séances n’auront')} plus aucun professeur</strong>
          {' '}(elles restent planifiées) :{' '}
          {impact.seances_sans_professeur_liste.slice(0, APERCU_SEANCES).map((s) => `${s.classe || 'Classe'} ${formatDateCourte(s.date)}`).join(', ')}
          {impact.seances_sans_professeur > APERCU_SEANCES && ` et ${impact.seances_sans_professeur - APERCU_SEANCES} autre(s)`}
        </Banner>
      )}
      {impact.classes_sans_autre_professeur.length > 0 && (
        <Banner tone="warning">
          Dernier professeur de : <strong>{impact.classes_sans_autre_professeur.join(', ')}</strong> — pensez à en assigner un autre.
        </Banner>
      )}
      {d.remplacement > 0 && (
        <Banner tone="warning">
          {pronom === 'il' ? 'Il' : 'Elle'} remplaçait un collègue sur <strong>{pluriel(d.remplacement, 'séance')}</strong> : le collègue y reste absent,
          la séance passe en « remplaçant à trouver ».
        </Banner>
      )}
    </>
  );
}

/**
 * PROF-02 : archiver un professeur (statut technique `inactif`). Le nettoyage des séances à venir est obligatoire.
 *
 * @param {object} props
 * @param {object} props.professeur
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onDone
 */
export function ArchiverProfesseurModal({ professeur, onClose, onDone }) {
  const { impact, erreur: erreurImpact, recharger } = useImpact(`/professeurs/${professeur.id}/impact-desactivation`);
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  async function confirmer() {
    setEnvoi(true);
    setErreur(null);
    try {
      const res = await client.post(`/professeurs/${professeur.id}/desactiver`);
      const n = res.data.seances_liberees ?? 0;
      onDone(`${nomComplet(professeur)} archivé(e)${n > 0 ? ` — ${pluriel(n, 'séance à venir libérée', 'séances à venir libérées')}` : ''}.`);
    } catch (err) {
      setErreur(getStatus(err) === 409 ? getErrorMessage(err) : getErrorMessage(err, 'L’archivage a échoué, rien n’a été modifié. Réessayez.'));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title={`Archiver ${nomComplet(professeur)} ?`}
      size="md"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
          <AdminButton onClick={confirmer} loading={envoi} disabled={!impact}>Archiver le professeur</AdminButton>
        </>
      }
    >
      <div style={{ display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.md }}>
        {erreur && <Banner tone="error">{erreur}</Banner>}
        {erreurImpact && <ErreurChargement texte="Impossible de calculer l'impact." onReessayer={recharger} />}
        {!impact && !erreurImpact && <Chargement texte="Calcul de l'impact…" />}
        {impact && (
          <>
            <p style={{ margin: 0 }}>
              Ce professeur ne pourra plus se connecter et sera déconnecté immédiatement. Son historique (heures, fiches de défraiement, tarifs, séances passées) est conservé.
            </p>
            <ImpactSeances impact={impact} pronom="il" />
            {impact.seances_conservees_avec_heures > 0 && (
              <Banner tone="info">
                {pluriel(impact.seances_conservees_avec_heures, 'séance à venir', 'séances à venir')} avec des heures déjà encodées {impact.seances_conservees_avec_heures > 1 ? 'restent' : 'reste'} à son nom.
              </Banner>
            )}
            {impact.heures_en_attente > 0 && (
              <Banner tone="info">
                {pluriel(impact.heures_en_attente, 'encodage d’heures non finalisé', 'encodages d’heures non finalisés')} : vous pourrez toujours les valider.
              </Banner>
            )}
          </>
        )}
      </div>
    </AdminModal>
  );
}

function ResumeHeures({ heures }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(110px, 1fr))', gap: ADMIN_SPACING.sm }}>
      {heures.par_statut.map((s) => (
        <div key={s.statut} style={{ background: 'var(--c-hover)', borderRadius: '6px', padding: '8px', textAlign: 'center', fontSize: '12px', color: 'var(--c-text-2)' }}>
          <strong style={{ display: 'block', fontSize: '20px', color: 'var(--c-text)' }}>{s.nb}</strong>
          {(STATUTS_TIMESHEET[s.statut]?.label || s.statut).toLowerCase()} · {formatHeures(s.total)}
        </div>
      ))}
    </div>
  );
}

const libelleFiches = (fiches) => fiches.slice(0, 3).map((f) => `${MOIS_LONGS[f.mois - 1]} ${f.annee}`).join(', ') + (fiches.length > 3 ? '…' : '');

/**
 * PROF-02 : suppression d'un professeur. Le serveur décide du cas :
 * A. aucune heure → confirmation simple ; B. heures → « a un historique » puis forçage (motif, nom, case) ;
 * C. heures sur une fiche générée sans être admin → suppression impossible, seul l'archivage est proposé.
 *
 * @param {object} props
 * @param {object} props.professeur
 * @param {(() => void)|null} props.onArchiver  ouvre l'archivage (null si déjà archivé)
 * @param {() => void} props.onClose
 * @param {(message: string) => void} props.onSupprime
 */
export function SupprimerProfesseurModal({ professeur, onArchiver, onClose, onSupprime }) {
  const { impact: resume, erreur: erreurResume, recharger } = useImpact(`/professeurs/${professeur.id}/impact-suppression`);
  const [etape, setEtape] = useState('resume'); // 'resume' | 'forcer'
  const [motif, setMotif] = useState('');
  const [nom, setNom] = useState('');
  const [compris, setCompris] = useState(false);
  const [envoi, setEnvoi] = useState(false);
  const [erreurs, setErreurs] = useState({});
  const [message, setMessage] = useState(null);
  const nomAttendu = nomComplet(professeur);
  const pret = motif.trim().length >= MOTIF_MIN && nom.trim() !== '' && compris;

  async function supprimer(payload) {
    setEnvoi(true);
    setErreurs({});
    setMessage(null);
    try {
      await client.delete(`/professeurs/${professeur.id}`, { data: payload });
      const n = resume?.impact?.seances_sans_professeur ?? 0;
      onSupprime(`${nomAttendu} supprimé(e).${n > 0 ? ` ${pluriel(n, 'séance à venir est', 'séances à venir sont')} désormais sans professeur.` : ''}`);
    } catch (err) {
      setErreurs(getFieldErrors(err));
      if (getStatus(err) === 409 && getErrorData(err)?.resume) {
        // L'état a changé depuis l'ouverture (heures encodées entre-temps) : on repart du résumé serveur.
        setEtape('resume');
        recharger();
      }
      setMessage(getStatus(err) === 422 && Object.keys(getFieldErrors(err)).length ? null : getErrorMessage(err, 'La suppression a échoué, rien n’a été supprimé. Réessayez.'));
    } finally {
      setEnvoi(false);
    }
  }

  const boutonArchiver = (variante = 'primary') => onArchiver && (
    <AdminButton variant={variante} onClick={onArchiver}>Archiver le professeur</AdminButton>
  );

  if (!resume) {
    return (
      <AdminModal isOpen title={`Supprimer ${nomAttendu} ?`} size="md" onClose={onClose}
        footer={<AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>}>
        {erreurResume
          ? <ErreurChargement texte="Impossible de vérifier les données liées." onReessayer={recharger} />
          : <Chargement texte="Vérification des données liées…" />}
      </AdminModal>
    );
  }

  const corps = (children) => <div style={{ display: 'flex', flexDirection: 'column', gap: ADMIN_SPACING.md }}>{message && <Banner tone="error">{message}</Banner>}{children}</div>;

  // A. Aucune heure encodée : confirmation simple.
  if (!resume.forcage_requis) {
    return (
      <AdminModal
        isOpen
        title={`Supprimer ${nomAttendu} ?`}
        size="md"
        onClose={onClose}
        closeOnBackdrop={false}
        footer={
          <>
            <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
            <AdminButton variant="danger" onClick={() => supprimer({})} loading={envoi}>Supprimer</AdminButton>
          </>
        }
      >
        {corps(
          <>
            <p style={{ margin: 0 }}>Son compte de connexion et sa fiche seront supprimés définitivement. Les séances restent planifiées.</p>
            <ImpactSeances impact={resume.impact} pronom="il" />
          </>,
        )}
      </AdminModal>
    );
  }

  // C. Heures sur une fiche générée, utilisateur non admin : bloqué.
  if (!resume.forcable) {
    return (
      <AdminModal
        isOpen
        title="Suppression impossible"
        size="md"
        onClose={onClose}
        footer={<><AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>{boutonArchiver()}</>}
      >
        {corps(
          <>
            <Banner tone="error">
              Impossible de supprimer : {pluriel(resume.fiches.length, 'fiche de défraiement a', 'fiches de défraiement ont')} déjà été générée{resume.fiches.length > 1 ? 's' : ''}
              {resume.fiches.length > 0 && ` (${libelleFiches(resume.fiches)})`}. Ces documents de paie doivent être conservés ; seul un administrateur peut forcer la suppression.
            </Banner>
            <p style={{ margin: 0 }}>
              {onArchiver
                ? 'Archivez ce professeur : il ne pourra plus se connecter, ses séances à venir seront libérées et son historique restera disponible.'
                : 'Ce professeur est déjà archivé : son historique reste disponible.'}
            </p>
          </>,
        )}
      </AdminModal>
    );
  }

  // B. Heures encodées : historique, puis forçage.
  if (etape === 'resume') {
    return (
      <AdminModal
        isOpen
        title="Ce professeur a un historique"
        size="md"
        onClose={onClose}
        footer={
          <>
            <AdminButton variant="ghost" size="sm" onClick={() => setEtape('forcer')} style={{ marginRight: 'auto', color: 'var(--tone-error-fg)' }}>
              Supprimer quand même…
            </AdminButton>
            <AdminButton variant="secondary" onClick={onClose}>Annuler</AdminButton>
            {boutonArchiver()}
          </>
        }
      >
        {corps(
          <>
            <p style={{ margin: 0 }}>
              {nomAttendu} a déjà des heures encodées.{' '}
              {onArchiver
                ? <>Pour un départ, <strong>archivez-le</strong> : tout est conservé et il ne peut plus se connecter.</>
                : 'Il est archivé : son historique est conservé.'}
            </p>
            <h3 style={{ margin: 0, fontSize: '13px', textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--c-text-2)' }}>
              Heures encodées ({resume.heures.nb} · {formatHeures(resume.heures.total)})
            </h3>
            <ResumeHeures heures={resume.heures} />
            <ul style={{ margin: 0, paddingLeft: ADMIN_SPACING.lg }}>
              <li>{pluriel(resume.tarifs, 'tarif horaire', 'tarifs horaires')}</li>
              <li>{pluriel(resume.seances_passees, 'séance passée', 'séances passées')} où il apparaît</li>
              <li>{pluriel(resume.impact.classes_actives, 'classe active', 'classes actives')} · {pluriel(resume.impact.seances_a_venir, 'séance à venir', 'séances à venir')}</li>
            </ul>
            {resume.heures_generees > 0 && (
              <Banner tone="warning">
                <strong>Administrateur :</strong> {pluriel(resume.fiches.length, 'fiche de défraiement générée', 'fiches de défraiement générées')}
                {resume.fiches.length > 0 && ` (${libelleFiches(resume.fiches)})`} seront aussi supprimées, avec leurs signatures.
              </Banner>
            )}
          </>,
        )}
      </AdminModal>
    );
  }

  return (
    <AdminModal
      isOpen
      title="Supprimer définitivement ?"
      size="md"
      onClose={onClose}
      closeOnBackdrop={false}
      footer={
        <>
          <AdminButton variant="secondary" onClick={() => setEtape('resume')}>← Retour</AdminButton>
          {boutonArchiver('secondary')}
          <AdminButton type="submit" form="form-supprimer-professeur" variant="danger" disabled={!pret} loading={envoi}>
            Supprimer définitivement
          </AdminButton>
        </>
      }
    >
      <form id="form-supprimer-professeur" noValidate onSubmit={(e) => { e.preventDefault(); if (pret) supprimer({ force: true, motif: motif.trim(), confirmation_nom: nom.trim() }); }}>
        {corps(
          <>
            <Banner tone="error">
              <strong>Action irréversible.</strong> Ses {pluriel(resume.heures.nb, 'heure encodée', 'heures encodées')}, ses tarifs,
              {resume.heures_generees > 0 && ' ses fiches de défraiement et signatures,'} son compte
              {resume.seances_passees > 0 && ` et sa présence sur ${pluriel(resume.seances_passees, 'séance passée', 'séances passées')}`} seront effacés.
              {' '}Les séances à venir restent planifiées, sans lui
              {resume.impact.seances_sans_professeur > 0 && ` (${pluriel(resume.impact.seances_sans_professeur, 'n’aura', 'n’auront')} plus aucun professeur)`}.
            </Banner>
            <AdminFormField label="Motif" htmlFor="supprimer-motif" required error={erreurs.motif} description={`Au moins ${MOTIF_MIN} caractères, conservé dans le journal de l'application.`}>
              <AdminTextarea id="supprimer-motif" rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} />
            </AdminFormField>
            <AdminFormField
              label="Nom du professeur"
              htmlFor="supprimer-nom"
              required
              error={erreurs.confirmation_nom}
              description={<>Recopiez « <strong>{nomAttendu}</strong> » pour confirmer.</>}
            >
              <AdminInput id="supprimer-nom" value={nom} onChange={(e) => setNom(e.target.value)} autoComplete="off" />
            </AdminFormField>
            <AdminCheckbox id="supprimer-compris" label="Je comprends que cette action est irréversible" checked={compris} onChange={(e) => setCompris(e.target.checked)} />
          </>,
        )}
      </form>
    </AdminModal>
  );
}
