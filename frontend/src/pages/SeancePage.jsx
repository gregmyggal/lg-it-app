import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import AdminModal from '../components/AdminModal';
import AdminButton from '../components/AdminButton';
import Banner from '../components/ui/Banner';
import StatutBadge from '../components/ui/StatutBadge';
import { ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import ChoisirProfesseurModal from '../components/seances/ChoisirProfesseurModal';
import ImpactModal from '../components/seances/ImpactModal';
import { AdminFormField, AdminInput } from '../components/AdminFormField';
import {
  ajouterASeance,
  annulerRemplacement,
  annulerSeance,
  modifierSeance,
  remplacerSurSeance,
  retirerDeSeance,
  useSeance,
} from '../hooks/useDirection';
import { getErrorMessage } from '../api/errors';
import { formatDateLongue, formatHoraire } from '../utils/dates';
import { STATUTS_SESSION, ROLES_PROFESSEUR, ORIGINES_SESSION } from '../utils/statuts';

const MOTIFS = ['Professeur malade', 'Météo', 'Salle indisponible'];
const DELAI_ANNULATION_MS = 5000;

/** Fiche d'une séance (UI-M6, staff) : professeurs, remplacer / ajouter / modifier / annuler, avec l'impact énoncé avant d'agir. */
export default function SeancePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const seance = useSeance(id);
  const s = seance.data;
  const [etape, setEtape] = useState(null); // { type, cible?, candidat? }
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [annulable, setAnnulable] = useState(null);
  const minuteur = useRef(null);

  useEffect(() => () => clearTimeout(minuteur.current), []);

  const actifs = (s?.professeurs || []).filter((p) => !p.remplace);
  const fermer = () => {
    setEtape(null);
    setErreur(null);
  };

  async function agir(fn, message, annuler) {
    setEnvoi(true);
    setErreur(null);
    try {
      await fn();
      fermer();
      seance.reload();
      clearTimeout(minuteur.current);
      setAnnulable({ message, annuler });
      minuteur.current = setTimeout(() => setAnnulable(null), DELAI_ANNULATION_MS);
    } catch (err) {
      setErreur(getErrorMessage(err));
    } finally {
      setEnvoi(false);
    }
  }

  async function defaire() {
    const { annuler } = annulable;
    clearTimeout(minuteur.current);
    setAnnulable(null);
    try {
      await annuler();
    } catch (err) {
      setErreur(getErrorMessage(err));
    }
    seance.reload();
  }

  const conflitsLignes = (candidat) =>
    candidat.conflits.map((c) => ({ type: 'alerte', texte: `${candidat.nom} a déjà « ${c.classe} » de ${c.heure_debut} à ${c.heure_fin}. Vous pouvez continuer.` }));

  return (
    <>
      <AdminPageHeader
        icon="📍"
        title={s ? `${s.cours?.titre ?? 'Séance'} · ${s.libelle_complet}` : 'Séance'}
        description={s ? `${formatDateLongue(s.date)} · ${formatHoraire(s.heure_debut, s.heure_fin)}${s.lieu ? ` · ${s.lieu}` : ''}` : undefined}
        action={
          <AdminButton variant="secondary" size="sm" onClick={() => navigate(-1)}>
            ← Retour
          </AdminButton>
        }
      />
      <AdminPageContent>
        <div className="mm">
          {seance.loading && !s && <LoadingBlock message="Chargement de la séance…" lignes={4} />}
          {seance.error && !s && <ErrorBlock message="Impossible de charger la séance. Vérifiez votre connexion puis réessayez." onRetry={seance.reload} />}
          {erreur && !etape && (
            <Banner tone="error" role="alert">
              <strong>{erreur}</strong>
            </Banner>
          )}

          {s && (
            <>
              <p style={{ margin: '0 0 12px' }}>
                <StatutBadge table={STATUTS_SESSION} valeur={s.statut} />
                {s.statut === 'annulee' && s.motif_annulation && <span style={{ marginLeft: 8, fontSize: 13 }}>Motif : {s.motif_annulation}</span>}
              </p>

              <section aria-label="Professeurs">
                <h2 className="dir-groupe">Professeurs</h2>
                {(s.professeurs || []).length === 0 && (
                  <Banner tone="warning">
                    <strong>Aucun professeur sur cette séance.</strong> Utilisez « Ajouter ».
                  </Banner>
                )}
                <div className="mm-carte" style={{ cursor: 'default' }}>
                  {(s.professeurs || []).map((p) => (
                    <div key={p.id} className="prof-ligne">
                      <span className="choix-prof__avatar" aria-hidden="true">
                        {p.nom.split(' ').map((m) => m[0]).slice(0, 2).join('')}
                      </span>
                      <div className="prof-ligne__texte">
                        <strong style={p.remplace ? { textDecoration: 'line-through' } : undefined}>{p.nom}</strong>
                        <small>
                          {p.remplace ? <StatutBadge label="Remplacé(e)" tone="neutral" /> : <StatutBadge table={ROLES_PROFESSEUR} valeur={p.role} />}
                          {!p.remplace && p.origine !== 'classe' && <StatutBadge table={ORIGINES_SESSION} valeur={p.origine} />}
                          {p.remplacant_a_trouver && <StatutBadge label="Remplaçant à trouver" tone="warning" />}
                        </small>
                      </div>
                      {p.telephone && !p.remplace && (
                        <a className="ib-lien" href={`tel:${p.telephone.replace(/\s/g, '')}`} aria-label={`Appeler ${p.nom}`}>
                          📞
                        </a>
                      )}
                      {p.remplace && !p.remplacant_a_trouver && s.statut !== 'annulee' && (
                        <button type="button" className="mm-lien" onClick={() => agir(() => annulerRemplacement(s.id, p.id), 'Remplacement annulé.', null)}>
                          Annuler le remplacement
                        </button>
                      )}
                      {!p.remplace && p.origine === 'ajout' && s.statut !== 'annulee' && (
                        <button type="button" className="mm-lien" onClick={() => agir(() => retirerDeSeance(s.id, p.id), `${p.nom} retiré(e) de la séance.`, () => ajouterASeance(s.id, p.id))}>
                          Retirer
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              </section>

              {s.statut !== 'annulee' && (
                <div className="seance-actions" role="group" aria-label="Actions sur la séance">
                  <button type="button" disabled={actifs.length === 0} onClick={() => setEtape({ type: actifs.length === 1 ? 'remplacer-choix' : 'qui', cible: actifs[0] })}>
                    Remplacer
                  </button>
                  <button type="button" onClick={() => setEtape({ type: 'ajouter-choix' })}>
                    Ajouter
                  </button>
                  <button type="button" disabled={!s.can?.update} onClick={() => setEtape({ type: 'modifier' })}>
                    Modifier
                  </button>
                  <button type="button" disabled={!s.can?.cancel} onClick={() => setEtape({ type: 'annuler' })}>
                    Annuler
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </AdminPageContent>

      {etape?.type === 'qui' && (
        <AdminModal isOpen title="Qui faut-il remplacer ?" size="sm" onClose={fermer}>
          <ul className="choix-prof__liste">
            {actifs.map((p) => (
              <li key={p.id}>
                <button type="button" className="choix-prof" onClick={() => setEtape({ type: 'remplacer-choix', cible: p })}>
                  <strong>{p.nom}</strong>
                </button>
              </li>
            ))}
          </ul>
        </AdminModal>
      )}

      {etape?.type === 'remplacer-choix' && (
        <ChoisirProfesseurModal
          sessionId={s.id}
          titre="Choisir le remplaçant"
          sousTitre={`${s.cours?.titre ?? ''} · ${formatHoraire(s.heure_debut, s.heure_fin)} · remplace ${etape.cible.nom}`}
          onChoisir={(candidat) => setEtape({ type: 'remplacer', cible: etape.cible, candidat })}
          onClose={fermer}
        />
      )}
      {etape?.type === 'remplacer' && (
        <ImpactModal
          titre={`${etape.candidat.nom} remplace ${etape.cible.nom}`}
          sousTitre={`${s.cours?.titre ?? ''} · ${formatDateLongue(s.date)} · ${formatHoraire(s.heure_debut, s.heure_fin)}`}
          erreur={erreur}
          envoi={envoi}
          verbe="Remplacer"
          lignes={[
            { texte: `${etape.cible.nom} est remplacé(e) sur cette séance` },
            { texte: `${etape.candidat.nom} est prévenu(e) par mail et par notification` },
            { type: 'neutre', texte: 'Les heures déjà encodées ne bougent pas' },
            ...conflitsLignes(etape.candidat),
          ]}
          onClose={fermer}
          onConfirmer={() =>
            agir(
              () => remplacerSurSeance(s.id, etape.cible.id, etape.candidat.id),
              `${etape.candidat.nom} remplace ${etape.cible.nom}.`,
              () => annulerRemplacement(s.id, etape.cible.id),
            )
          }
        />
      )}

      {etape?.type === 'ajouter-choix' && (
        <ChoisirProfesseurModal
          sessionId={s.id}
          titre="Ajouter un professeur"
          sousTitre="Il s'ajoute à cette séance seulement, sans remplacer personne."
          onChoisir={(candidat) => setEtape({ type: 'ajouter', candidat })}
          onClose={fermer}
        />
      )}
      {etape?.type === 'ajouter' && (
        <ImpactModal
          titre={`Ajouter ${etape.candidat.nom}`}
          sousTitre={`${s.cours?.titre ?? ''} · ${formatDateLongue(s.date)}`}
          erreur={erreur}
          envoi={envoi}
          verbe="Ajouter"
          lignes={[
            { texte: `${etape.candidat.nom} intervient sur cette séance en plus des autres professeurs` },
            { type: 'neutre', texte: 'Aucun mail n\'est envoyé pour un simple ajout' },
            ...conflitsLignes(etape.candidat),
          ]}
          onClose={fermer}
          onConfirmer={() => agir(() => ajouterASeance(s.id, etape.candidat.id), `${etape.candidat.nom} ajouté(e).`, () => retirerDeSeance(s.id, etape.candidat.id))}
        />
      )}

      {etape?.type === 'modifier' && s && <ModifierSeance s={s} erreur={erreur} envoi={envoi} onClose={fermer} onEnregistrer={(p) => agir(() => modifierSeance(s.id, p), 'Séance modifiée.', () => modifierSeance(s.id, { date: s.date, heure_debut: s.heure_debut, heure_fin: s.heure_fin, lieu: s.lieu }))} />}
      {etape?.type === 'annuler' && s && <AnnulerSeance erreur={erreur} envoi={envoi} onClose={fermer} onAnnuler={(motif) => agir(() => annulerSeance(s.id, motif), 'Séance annulée.', null)} />}

      {annulable && (
        <div className="auj-toast" role="status" aria-live="polite">
          <span>{annulable.message}</span>
          {annulable.annuler && (
            <button type="button" onClick={defaire}>
              Annuler
            </button>
          )}
        </div>
      )}
    </>
  );
}

function ModifierSeance({ s, erreur, envoi, onClose, onEnregistrer }) {
  const [date, setDate] = useState(s.date);
  const [debut, setDebut] = useState(s.heure_debut);
  const [fin, setFin] = useState(s.heure_fin);
  const [lieu, setLieu] = useState(s.lieu || '');
  return (
    <AdminModal
      isOpen
      title="Modifier la séance"
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Annuler
          </AdminButton>
          <AdminButton loading={envoi} onClick={() => onEnregistrer({ date, heure_debut: debut, heure_fin: fin, lieu: lieu || null })}>
            Enregistrer
          </AdminButton>
        </>
      }
    >
      <p style={{ margin: '0 0 12px', fontSize: 13, color: 'var(--c-text-2)' }}>Seulement cette séance : les suivantes ne bougent pas (pour décaler la suite, utilisez l'écran de la classe).</p>
      {erreur && (
        <Banner tone="error" role="alert">
          <strong>{erreur}</strong>
        </Banner>
      )}
      <AdminFormField label="Date" htmlFor="seance-date">
        <AdminInput id="seance-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
      </AdminFormField>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
        <AdminFormField label="Début" htmlFor="seance-debut">
          <AdminInput id="seance-debut" type="time" value={debut} onChange={(e) => setDebut(e.target.value)} />
        </AdminFormField>
        <AdminFormField label="Fin" htmlFor="seance-fin">
          <AdminInput id="seance-fin" type="time" value={fin} onChange={(e) => setFin(e.target.value)} />
        </AdminFormField>
      </div>
      <AdminFormField label="Lieu" htmlFor="seance-lieu">
        <AdminInput id="seance-lieu" value={lieu} onChange={(e) => setLieu(e.target.value)} />
      </AdminFormField>
    </AdminModal>
  );
}

function AnnulerSeance({ erreur, envoi, onClose, onAnnuler }) {
  const [motif, setMotif] = useState('');
  return (
    <AdminModal
      isOpen
      title="Annuler la séance ?"
      size="sm"
      onClose={onClose}
      footer={
        <>
          <AdminButton variant="secondary" onClick={onClose}>
            Retour
          </AdminButton>
          <AdminButton variant="danger" loading={envoi} disabled={motif.trim().length < 3} onClick={() => onAnnuler(motif.trim())}>
            Annuler la séance
          </AdminButton>
        </>
      }
    >
      {erreur && (
        <Banner tone="error" role="alert">
          <strong>{erreur}</strong>
        </Banner>
      )}
      <div className="pm-puces" role="group" aria-label="Motifs rapides" style={{ flexWrap: 'wrap', overflow: 'visible' }}>
        {MOTIFS.map((m) => (
          <button key={m} type="button" className={`pm-puce${motif === m ? ' pm-puce--on' : ''}`} onClick={() => setMotif(m)}>
            {m}
          </button>
        ))}
      </div>
      <AdminFormField label="Motif (obligatoire)" htmlFor="seance-motif">
        <AdminInput id="seance-motif" value={motif} onChange={(e) => setMotif(e.target.value)} />
      </AdminFormField>
      <ul className="impact">
        <li className="impact--alerte">Les heures non validées liées à cette séance seront supprimées ; si des heures sont déjà validées, l'annulation est refusée.</li>
        <li className="impact--neutre">Les familles ne sont pas prévenues automatiquement.</li>
      </ul>
    </AdminModal>
  );
}
