import { useMemo, useRef, useState } from 'react';
import { PartagerModal, PartagesTab } from './PartagesStage';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import Banner from '../ui/Banner';
import { Section } from '../ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import { useDepot, deposerFichiers, supprimerFichier, telechargerFichier } from '../../hooks/useDepot';
import { useToast } from '../../hooks/useToast';
import { getErrorData, getErrorMessage } from '../../api/errors';
import { formatDateCourte } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const SANS_LIBELLE = 'Sans libellé';

function formatTaille(octets) {
  if (octets >= 1048576) return `${(octets / 1048576).toFixed(1).replace('.', ',')} Mo`;
  return `${Math.max(1, Math.round(octets / 1024))} Ko`;
}

function libellePortee(f) {
  return f.portee === 'seance' ? `Séance ${f.seance_numero}` : 'Stage entier';
}

/**
 * DEP-01 T1 — Section « Fichiers » d'un stage (mock-up 01) : réalisations des enfants, groupées par libellé,
 * dépôt dans le stage entier ou une séance. Staff et professeurs du stage.
 */
export default function FichiersStage({ classeId }) {
  const depot = useDepot(classeId);
  const toast = useToast();
  const [filtre, setFiltre] = useState('tout'); // 'tout' | 'stage' | id de séance
  const [ouvert, setOuvert] = useState(false);
  const [aSupprimer, setASupprimer] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [onglet, setOnglet] = useState('fichiers'); // 'fichiers' | 'partages'
  const [selection, setSelection] = useState(() => new Set());
  const [aPartager, setAPartager] = useState(null); // fichiers de la sélection à partager

  const fichiers = useMemo(() => depot.data?.data || [], [depot.data]);
  const seances = depot.data?.meta?.seances || [];

  const visibles = useMemo(
    () =>
      fichiers.filter((f) => {
        if (filtre === 'tout') return true;
        if (filtre === 'stage') return f.portee === 'stage';
        return f.course_session_id === filtre;
      }),
    [fichiers, filtre],
  );

  const groupes = useMemo(() => {
    const parLibelle = new Map();
    visibles.forEach((f) => {
      const cle = f.libelle || SANS_LIBELLE;
      if (!parLibelle.has(cle)) parLibelle.set(cle, []);
      parLibelle.get(cle).push(f);
    });
    // Les fichiers sans libellé en dernier.
    return [...parLibelle.entries()].sort(([a], [b]) => (a === SANS_LIBELLE) - (b === SANS_LIBELLE) || a.localeCompare(b, 'fr'));
  }, [visibles]);

  function basculer(ids, coche) {
    setSelection((prev) => {
      const suivant = new Set(prev);
      ids.forEach((id) => (coche ? suivant.add(id) : suivant.delete(id)));
      return suivant;
    });
  }

  const partager = (liste) => setAPartager(liste);

  async function telecharger(f) {
    try {
      await telechargerFichier(classeId, f);
    } catch (err) {
      toast.error(getErrorMessage(err, 'Téléchargement impossible. Réessayez.'));
    }
  }

  async function supprimer() {
    setEnCours(true);
    try {
      await supprimerFichier(classeId, aSupprimer.id);
      toast.success('Fichier supprimé.');
      setASupprimer(null);
      setSelection((prev) => {
        const suivant = new Set(prev);
        suivant.delete(aSupprimer.id);
        return suivant;
      });
      depot.reload();
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setEnCours(false);
    }
  }

  const filtres = [
    { valeur: 'tout', label: 'Tout' },
    { valeur: 'stage', label: 'Stage' },
    ...seances.map((s) => ({ valeur: s.id, label: `Séance ${s.seance_numero}` })),
  ];

  return (
    <Section
      title="Fichiers"
      subtitle={depot.data ? `${fichiers.length} fichier${fichiers.length > 1 ? 's' : ''}` : undefined}
      actions={<AdminButton size="sm" onClick={() => setOuvert(true)}>Déposer des fichiers</AdminButton>}
    >
      <div role="group" aria-label="Section" style={{ display: 'flex', gap: ADMIN_SPACING.sm, marginBottom: ADMIN_SPACING.lg }}>
        <AdminButton size="sm" variant={onglet === 'fichiers' ? 'primary' : 'secondary'} aria-pressed={onglet === 'fichiers'} onClick={() => setOnglet('fichiers')}>
          Fichiers
        </AdminButton>
        <AdminButton size="sm" variant={onglet === 'partages' ? 'primary' : 'secondary'} aria-pressed={onglet === 'partages'} onClick={() => setOnglet('partages')}>
          Partages
        </AdminButton>
      </div>

      {onglet === 'partages' && <PartagesTab classeId={classeId} />}

      {onglet === 'fichiers' && depot.loading && <LoadingBlock message="Chargement des fichiers…" lignes={3} />}
      {onglet === 'fichiers' && depot.error && <ErrorBlock message={depot.error} onRetry={depot.reload} />}

      {onglet === 'fichiers' && depot.data && fichiers.length === 0 && (
        <EmptyBlock icon="📁" title="Aucun fichier pour l'instant">
          Déposez ici les réalisations des enfants (projets, créations). Les parents pourront les recevoir par lien plus tard.
        </EmptyBlock>
      )}

      {onglet === 'fichiers' && fichiers.length > 0 && (
        <>
          <div role="group" aria-label="Filtrer par espace" style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', marginBottom: ADMIN_SPACING.lg }}>
            {filtres.map((f) => (
              <AdminButton key={f.valeur} size="sm" variant={filtre === f.valeur ? 'primary' : 'secondary'} aria-pressed={filtre === f.valeur} onClick={() => setFiltre(f.valeur)}>
                {f.label}
              </AdminButton>
            ))}
          </div>

          {groupes.length === 0 && <p style={{ fontSize: '13px' }}>Aucun fichier dans cet espace.</p>}
          <div style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
            {groupes.map(([libelle, liste]) => (
              <div key={libelle}>
                <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', marginBottom: ADMIN_SPACING.sm }}>
                  <label style={{ display: 'flex', gap: ADMIN_SPACING.sm, alignItems: 'center', fontSize: '14px', fontWeight: 700 }}>
                    <input
                      type="checkbox"
                      aria-label={`Sélectionner tous les fichiers de ${libelle}`}
                      checked={liste.every((f) => selection.has(f.id))}
                      onChange={(e) => basculer(liste.map((f) => f.id), e.target.checked)}
                    />
                    <span>
                      {libelle} <span style={{ color: ADMIN_COLORS.textSecondary, fontWeight: 400 }}>· {liste.length} fichier{liste.length > 1 ? 's' : ''}</span>
                    </span>
                  </label>
                  <AdminButton size="sm" onClick={() => partager(liste)}>Partager</AdminButton>
                </div>
                <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.sm }}>
                  {liste.map((f) => (
                    <li
                      key={f.id}
                      style={{
                        display: 'flex',
                        gap: ADMIN_SPACING.md,
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        border: `1px solid ${ADMIN_COLORS.border}`,
                        borderRadius: ADMIN_RADIUS.md,
                        padding: `${ADMIN_SPACING.sm} ${ADMIN_SPACING.md}`,
                        background: ADMIN_COLORS.cardBg,
                      }}
                    >
                      <input
                        type="checkbox"
                        aria-label={`Sélectionner ${f.nom}`}
                        checked={selection.has(f.id)}
                        onChange={(e) => basculer([f.id], e.target.checked)}
                      />
                      <div style={{ minWidth: 0, flex: '1 1 180px' }}>
                        <div style={{ fontWeight: 600, overflowWrap: 'anywhere' }}>{f.nom}</div>
                        <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary }}>
                          {libellePortee(f)} · {formatTaille(f.taille)} · déposé le {formatDateCourte(f.depose_le.slice(0, 10))}
                          {f.depose_par ? ` par ${f.depose_par}` : ''}
                        </div>
                      </div>
                      <div style={{ display: 'flex', gap: ADMIN_SPACING.sm }}>
                        <AdminButton size="sm" variant="secondary" onClick={() => telecharger(f)} aria-label={`Télécharger ${f.nom}`}>
                          Télécharger
                        </AdminButton>
                        <AdminButton size="sm" variant="secondary" onClick={() => setASupprimer(f)} aria-label={`Supprimer ${f.nom}`}>
                          Supprimer
                        </AdminButton>
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </>
      )}

      {onglet === 'fichiers' && selection.size > 0 && (
        <div
          role="status"
          style={{ position: 'sticky', bottom: ADMIN_SPACING.md, marginTop: ADMIN_SPACING.lg, display: 'flex', gap: ADMIN_SPACING.md, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', padding: ADMIN_SPACING.md, borderRadius: ADMIN_RADIUS.md, border: `1px solid ${ADMIN_COLORS.border}`, background: ADMIN_COLORS.cardBg }}
        >
          <span>{selection.size} fichier{selection.size > 1 ? 's' : ''} sélectionné{selection.size > 1 ? 's' : ''}</span>
          <span style={{ display: 'flex', gap: ADMIN_SPACING.sm }}>
            <AdminButton size="sm" variant="secondary" onClick={() => setSelection(new Set())}>Vider</AdminButton>
            <AdminButton size="sm" onClick={() => partager(fichiers.filter((f) => selection.has(f.id)))}>Partager à un parent</AdminButton>
          </span>
        </div>
      )}

      {aPartager && (
        <PartagerModal
          classeId={classeId}
          fichiers={aPartager}
          onClose={() => {
            setAPartager(null);
            setSelection(new Set());
          }}
        />
      )}

      {ouvert && (
        <DeposerModal
          classeId={classeId}
          meta={depot.data?.meta}
          onClose={() => setOuvert(false)}
          onDone={(message) => {
            toast.success(message);
            depot.reload();
          }}
        />
      )}

      <AdminModal
        isOpen={Boolean(aSupprimer)}
        title="Supprimer ce fichier ?"
        size="sm"
        onClose={() => setASupprimer(null)}
        footer={
          <>
            <AdminButton variant="secondary" onClick={() => setASupprimer(null)}>Annuler</AdminButton>
            <AdminButton variant="danger" onClick={supprimer} loading={enCours}>Supprimer définitivement</AdminButton>
          </>
        }
      >
        <p style={{ margin: 0 }}>« {aSupprimer?.nom} » sera supprimé définitivement. Cette action est irréversible.</p>
      </AdminModal>
    </Section>
  );
}

function DeposerModal({ classeId, meta, onClose, onDone }) {
  const inputRef = useRef(null);
  const [fichiers, setFichiers] = useState([]);
  const [libelle, setLibelle] = useState('');
  const [espace, setEspace] = useState('');
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [refuses, setRefuses] = useState([]);

  const maxMo = meta ? Math.round(meta.taille_max_octets / 1048576) : 10;
  const accept = meta ? meta.extensions.map((e) => `.${e}`).join(',') : undefined;

  async function deposer() {
    setEnvoi(true);
    setErreur(null);
    setRefuses([]);
    try {
      const res = await deposerFichiers(classeId, { fichiers, libelle: libelle.trim(), courseSessionId: espace });
      const n = res.data.length;
      onDone(`${n} fichier${n > 1 ? 's' : ''} déposé${n > 1 ? 's' : ''}${libelle.trim() ? ` pour ${libelle.trim()}` : ''}.`);
      if (res.refuses.length === 0) {
        onClose();
      } else {
        // Les valides sont déposés : on ne garde dans la sélection que les refusés, pour correction.
        setFichiers(fichiers.filter((f) => res.refuses.some((r) => r.nom === f.name)));
        setRefuses(res.refuses);
      }
    } catch (err) {
      setRefuses(getErrorData(err)?.refuses || []);
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminModal
      isOpen
      title="Déposer des fichiers"
      onClose={onClose}
      closeOnBackdrop={!envoi}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose} disabled={envoi}>Fermer</AdminButton>
          <AdminButton onClick={deposer} loading={envoi} disabled={fichiers.length === 0}>
            {fichiers.length > 1 ? `Déposer ${fichiers.length} fichiers` : 'Déposer'}
          </AdminButton>
        </>
      }
    >
      <Banner tone="warning">
        Déposez uniquement des <strong>réalisations</strong> (projets, créations). <strong>Pas de photos d'enfants.</strong>
      </Banner>

      <div style={{ marginTop: ADMIN_SPACING.lg, display: 'grid', gap: ADMIN_SPACING.lg }}>
        <div>
          <input
            ref={inputRef}
            id="depot-fichiers"
            type="file"
            multiple
            accept={accept}
            style={{ display: 'none' }}
            onChange={(e) => {
              setFichiers([...e.target.files]);
              setRefuses([]);
              setErreur(null);
            }}
          />
          <AdminButton variant="secondary" onClick={() => inputRef.current?.click()}>Choisir des fichiers</AdminButton>
          <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.xs }}>
            {meta ? `${meta.extensions.join(', ')} · ${maxMo} Mo maximum par fichier` : ''}
          </div>
          {fichiers.length > 0 && (
            <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '18px', fontSize: '13px' }} aria-live="polite">
              {fichiers.map((f) => (
                <li key={`${f.name}-${f.size}`}>{f.name} · {formatTaille(f.size)}</li>
              ))}
            </ul>
          )}
        </div>

        <div>
          <label htmlFor="depot-libelle" style={{ display: 'block', fontSize: '12px', fontWeight: 600, marginBottom: ADMIN_SPACING.xs }}>
            Libellé (facultatif)
          </label>
          <input
            id="depot-libelle"
            type="text"
            value={libelle}
            maxLength={60}
            placeholder="Prénom de l'enfant, ex. Léa"
            onChange={(e) => setLibelle(e.target.value)}
            style={{ width: '100%', boxSizing: 'border-box', padding: ADMIN_SPACING.sm, minHeight: '44px' }}
          />
          <div style={{ fontSize: '12px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.xs }}>
            Appliqué à tout le lot. Le prénom seul suffit : il servira à regrouper les fichiers d'un même enfant.
          </div>
        </div>

        <div>
          <label htmlFor="depot-espace" style={{ display: 'block', fontSize: '12px', fontWeight: 600, marginBottom: ADMIN_SPACING.xs }}>
            Déposer dans
          </label>
          <select
            id="depot-espace"
            value={espace}
            onChange={(e) => setEspace(e.target.value)}
            style={{ width: '100%', boxSizing: 'border-box', padding: ADMIN_SPACING.sm, minHeight: '44px' }}
          >
            <option value="">Stage entier</option>
            {(meta?.seances || []).map((s) => (
              <option key={s.id} value={s.id}>Séance {s.seance_numero} · {formatDateCourte(s.date)}</option>
            ))}
          </select>
        </div>

        {erreur && <Banner tone="error">{erreur}</Banner>}
        {refuses.length > 0 && (
          <Banner tone="error">
            <div>
              <strong>Non déposés :</strong>
              <ul style={{ margin: `${ADMIN_SPACING.xs} 0 0`, paddingLeft: '18px' }}>
                {refuses.map((r) => (
                  <li key={r.nom}>{r.nom} — {r.message}</li>
                ))}
              </ul>
            </div>
          </Banner>
        )}
      </div>
    </AdminModal>
  );
}
