import { Fragment, useMemo, useState } from 'react';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { Table, Th, Td, Tr } from '../ui/Table';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import AdapterSaisieModal from './AdapterSaisieModal';
import LisserMoisModal from './LisserMoisModal';
import ValiderLotModal from './ValiderLotModal';
import VersionsMois from './VersionsMois';
import FriseMois from './FriseMois';
import SoumettreALaPlaceModal from './SoumettreALaPlaceModal';
import NoteInterne, { NoteInterneMois } from './NotesInternes';
import { useMediaQuery } from '../../hooks/useMediaQuery';
import AdminModal from '../AdminModal';
import SignaturePreuve from '../signature/SignaturePreuve';
import EmployeurBadge from '../employeurs/EmployeurBadge';
import DefinirEmployeurModal from '../employeurs/DefinirEmployeurModal';
import EmployeurMoisSection from '../employeurs/EmployeurMoisSection';
import { AdminFormField, AdminTextarea } from '../AdminFormField';
import { apercuPdf, deverrouillerMois, forcerSoumission, genererPdf, remettreMoisEnBrouillon, telechargerPdf, traiterContestation, useDetailMois } from '../../hooks/useTimesheets';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import { useAuth } from '../../auth/AuthContext';
import { enregistrerBlob, ouvrirPdf } from '../../utils/telechargement';
import { STATUTS_MOIS_PROF, STATUTS_TIMESHEET, TYPES_ACTIVITE } from '../../utils/statuts';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { libelleSession } from '../../utils/classes';

const LIBELLE_CHAMP = { nombre_heures: 'Heures', date_prestation: 'Date', type_activite: 'Type' };

function texteAudit(h) {
  if (h.action === 'soumission_forcee') return 'Mois soumis par la direction à la place du professeur';
  if (h.action === 'remise_brouillon') return 'Remise en brouillon par la direction';
  if (h.action === 'contestation') return 'Contestation du professeur';
  if (h.action === 'reponse_contestation') return 'Réponse de la direction à la contestation';
  if (h.action === 'classe_supprimee') {
    const seance = h.avant.seance_numero ? ` : séance n° ${h.avant.seance_numero}${h.avant.date_seance ? ` du ${formatDateCourte(h.avant.date_seance)}` : ''} détachée` : '';
    return `Classe « ${h.avant.classe} » supprimée${seance}`;
  }
  if (h.action === 'lissage') {
    return `Lissage : ${formatHeures(h.apres.heures_deplacees)} (${formatEuros(h.apres.montant_deplace)}) déplacées vers le ${formatDateCourte(h.apres.date_cible)}`;
  }
  return Object.keys(h.apres).map((c) => `${LIBELLE_CHAMP[c] || c} ${h.avant[c]} → ${h.apres[c]}`).join(', ');
}

/** Calendrier du mois : montant par jour, en rouge au-delà du plafond. */
function CalendrierMois({ annee, mois, jours, plafond }) {
  const parDate = new Map(jours.map((j) => [j.date, j]));
  const nb = new Date(annee, mois, 0).getDate();
  const decalage = (new Date(annee, mois - 1, 1).getDay() + 6) % 7; // lundi = 0
  const cases = [...Array(decalage).fill(null), ...Array.from({ length: nb }, (_, i) => i + 1)];
  return (
    <div>
      <div role="grid" aria-label="Montant par jour" style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 3, fontSize: 11 }}>
        {['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((j, i) => <div key={i} style={{ textAlign: 'center', color: 'var(--c-text-2)' }}>{j}</div>)}
        {cases.map((n, i) => {
          if (n === null) return <div key={`v${i}`} />;
          const date = `${annee}-${String(mois).padStart(2, '0')}-${String(n).padStart(2, '0')}`;
          const j = parDate.get(date);
          const fond = j?.depasse ? 'var(--tone-error-bg)' : j?.brouillon ? 'var(--tone-warning-bg)' : j?.montant > 0 ? 'var(--tone-success-bg)' : 'var(--c-card)';
          return (
            <div
              key={date}
              title={j ? `${formatHeures(j.heures)}${j.brouillon ? ' (brouillon)' : ''} — ${formatEuros(j.montant)}${j.depasse ? ` — au-delà de ${formatEuros(plafond)}` : ''}` : undefined}
              style={{
                border: `1px solid ${j?.depasse ? ADMIN_COLORS.error : ADMIN_COLORS.border}`,
                background: fond,
                borderRadius: 5, padding: 3, minHeight: 44,
              }}
            >
              {n}
              {j && <div style={{ fontWeight: 600 }}>{formatHeures(j.heures)}{j.montant > 0 ? ` · ${formatEuros(j.montant)}` : ''}{j.depasse ? ' ⚠' : ''}</div>}
            </div>
          );
        })}
      </div>
      <p style={{ fontSize: 12, color: 'var(--c-text-2)' }}>
        Jaune : saisies en brouillon. Vert : saisies soumises. Rouge : au-delà de {formatEuros(plafond)} par jour.
      </p>
    </div>
  );
}

/**
 * Détail d'un professeur pour un mois (TS-01 T3) : saisies, calendrier avec jauge, historique, et actions de
 * validation, d'adaptation et de lissage. Le serveur reste l'autorité (droits, statuts, plafond).
 */
export default function DetailProfesseurMois({ professeurId, mois, onRetour, onChange }) {
  const toast = useToast();
  const { user } = useAuth();
  const [annee, moisNum] = mois.split('-').map(Number);
  const detail = useDetailMois(professeurId, annee, moisNum);
  const [adapter, setAdapter] = useState(null);
  const [lisser, setLisser] = useState(undefined); // undefined = fermé, null = auto, objet = saisie (manuel)
  const [valider, setValider] = useState(false);
  const [deverrouillage, setDeverrouillage] = useState(null); // null = fermé, sinon motif
  const [pdfEnCours, setPdfEnCours] = useState(false);
  const [reponse, setReponse] = useState(null); // null = fermé, sinon texte de la réponse
  const [brouillon, setBrouillon] = useState(null); // null = fermé, sinon motif
  const [envoiBrouillon, setEnvoiBrouillon] = useState(false);
  const [erreurBrouillon, setErreurBrouillon] = useState(null);
  const [forcer, setForcer] = useState(false);
  const [noteLigne, setNoteLigne] = useState(null); // id de la saisie dont la note interne est ouverte
  const mobile = useMediaQuery('(max-width: 860px)');
  const [envoiForcer, setEnvoiForcer] = useState(false);
  const [erreurForcer, setErreurForcer] = useState(null);
  const [employeurModal, setEmployeurModal] = useState(false);
  const [confirmationPdf, setConfirmationPdf] = useState(false);
  const [envoiReponse, setEnvoiReponse] = useState(false);
  const [erreurReponse, setErreurReponse] = useState(null);

  const d = detail.data;
  const soumises = useMemo(() => (d?.lignes || []).filter((t) => t.statut_validation === 'soumis' && t.can?.validate), [d]);
  const modifiees = useMemo(() => new Set((d?.historique || []).map((h) => h.timesheet_id)), [d]);
  const nbDepassements = d?.jours.filter((j) => j.depasse).length || 0;
  const totaux = useMemo(() => (d?.lignes || []).reduce((a, t) => ({ h: a.h + Number(t.nombre_heures), e: a.e + (Number(t.montant_brut) || 0) }), { h: 0, e: 0 }), [d]);

  const sf = d?.soumission_forcee;
  const contestation = useMemo(() => (d?.resume?.statut_mois === 'conteste' ? (d.historique || []).find((h) => h.action === 'contestation') : null), [d]);

  async function envoyerReponse() {
    setEnvoiReponse(true);
    setErreurReponse(null);
    try {
      await traiterContestation(professeurId, { annee, mois: moisNum, reponse: reponse.trim() });
      setReponse(null);
      recharger('Contestation traitée : les saisies repassent en revue et le professeur est prévenu.');
    } catch (err) {
      setErreurReponse(getErrorMessage(err));
    } finally {
      setEnvoiReponse(false);
    }
  }

  async function envoyerBrouillon() {
    setEnvoiBrouillon(true);
    setErreurBrouillon(null);
    try {
      const res = await remettreMoisEnBrouillon(professeurId, { annee, mois: moisNum, motif: brouillon.trim() });
      setBrouillon(null);
      recharger(`${res.remises_en_brouillon} ligne(s) remise(s) en brouillon. ${d.professeur.nom} a été prévenu(e).`);
    } catch (err) {
      setErreurBrouillon(getErrorMessage(err));
    } finally {
      setEnvoiBrouillon(false);
    }
  }

  async function envoyerSoumissionForcee(message) {
    setEnvoiForcer(true);
    setErreurForcer(null);
    try {
      await forcerSoumission(professeurId, { annee, mois: moisNum, motif: message });
      setForcer(false);
      recharger(`Mois soumis pour ${d.professeur.nom} : à valider.`);
    } catch (err) {
      if (err.response?.status === 409) {
        setForcer(false);
        recharger();
        toast.error('Ce mois vient d\'être soumis par le professeur. Le détail a été rechargé.');
      } else {
        setErreurForcer(getErrorMessage(err));
      }
    } finally {
      setEnvoiForcer(false);
    }
  }

  async function actionPdf(fn, succes) {
    setPdfEnCours(true);
    try {
      await fn();
      if (succes) {
        toast.success(succes);
        detail.reload();
        onChange?.();
      }
    } catch (err) {
      toast.error(getErrorMessage(err, 'Opération PDF impossible'));
    } finally {
      setPdfEnCours(false);
    }
  }

  const dernierPdf = d?.pdf?.versions?.[0];
  const telecharger = (pdf) => telechargerPdf(pdf.id).then((blob) => enregistrerBlob(blob, `${String(annee)}${String(moisNum).padStart(2, '0')} Fiche de défraiement_${d.professeur.nom}.pdf`));

  async function confirmerDeverrouillage() {
    await actionPdf(() => deverrouillerMois(professeurId, { annee, mois: moisNum, motif: deverrouillage.trim() }), 'Mois déverrouillé : une nouvelle version du PDF pourra être générée.');
    setDeverrouillage(null);
  }

  function recharger(message) {
    setAdapter(null);
    setLisser(undefined);
    setValider(false);
    if (message) toast.success(message);
    detail.reload();
    onChange?.();
  }

  return (
    <>
      <div style={{ marginBottom: ADMIN_SPACING.md }}>
        <AdminButton variant="secondary" size="sm" onClick={onRetour}>← Retour à la synthèse</AdminButton>
      </div>

      {detail.loading && !d && <LoadingBlock message="Chargement du détail…" />}
      {detail.error && <ErrorBlock message={detail.error} onRetry={detail.reload} />}

      {d && (
        <>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: ADMIN_SPACING.md, alignItems: 'center', justifyContent: 'space-between', marginBottom: ADMIN_SPACING.lg }}>
            <div>
              <h2 style={{ margin: 0 }}>{d.professeur.nom} — {new Date(annee, moisNum - 1, 1).toLocaleDateString('fr-BE', { month: 'long', year: 'numeric' })}</h2>
              <div style={{ marginTop: 6, display: 'flex', gap: 12, alignItems: 'center' }}>
                {d.resume && <StatutBadge table={STATUTS_MOIS_PROF} valeur={d.resume.statut_mois} />}
                <span>Total <strong>{formatEuros(totaux.e)}</strong> · {formatHeures(totaux.h)}</span>
              </div>
              {d.employeur && (
                <div style={{ marginTop: 8, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                  <span>Employeur :</span>
                  <EmployeurBadge employeur={d.employeur.employeur} source={d.employeur.source} verrouille={d.employeur.verrouille} raisonVerrou={d.employeur.raison_verrou} />
                  <AdminButton variant="secondary" size="sm" onClick={() => setEmployeurModal(true)}>
                    {d.employeur.modifiable ? 'Modifier' : 'Détails'}
                  </AdminButton>
                </div>
              )}
              <SignaturePreuve signature={d.signature} />
            </div>
            <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap' }}>
              {sf?.possible && (
                <AdminButton onClick={() => { setErreurForcer(null); setForcer(true); }}>Soumettre à la place du professeur…</AdminButton>
              )}
              <AdminButton
                variant={sf?.possible ? 'secondary' : undefined}
                onClick={() => setValider(true)}
                disabled={soumises.length === 0}
                title={soumises.length === 0 ? 'Aucune saisie soumise' : undefined}
              >
                Valider le mois ({soumises.length})
              </AdminButton>
              <AdminButton variant="secondary" disabled={pdfEnCours || d.lignes.length === 0} onClick={() => actionPdf(() => apercuPdf(professeurId, annee, moisNum).then(ouvrirPdf))}>Aperçu PDF</AdminButton>
              <AdminButton
                disabled={pdfEnCours || d.pdf.bloquants.length > 0}
                title={d.pdf.bloquants.join(' · ') || undefined}
                onClick={() => setConfirmationPdf(true)}
              >
                Générer le PDF
              </AdminButton>
              {dernierPdf && (
                <AdminButton variant="secondary" disabled={pdfEnCours} onClick={() => actionPdf(() => telecharger(dernierPdf))}>Télécharger (v{dernierPdf.version})</AdminButton>
              )}
              {d.remise_brouillon?.possible && (
                <AdminButton variant="secondary" disabled={pdfEnCours} onClick={() => setBrouillon('')}>Remettre en brouillon…</AdminButton>
              )}
              {user?.role === 'admin' && d.resume?.statut_mois === 'genere' && (
                <AdminButton variant="secondary" disabled={pdfEnCours} onClick={() => setDeverrouillage('')}>Déverrouiller…</AdminButton>
              )}
            </div>
          </div>
          {d.pdf.bloquants.length > 0 && d.lignes.length > 0 && (
            <p style={{ fontSize: 13, color: 'var(--tone-warning-fg)', margin: '0 0 12px' }}>PDF : {d.pdf.bloquants.join(' · ')}.</p>
          )}

          {d.employeur?.verrouille && (
            <Banner tone="info">
              <span role="img" aria-label="Mois verrouillé">🔒</span> {d.employeur.raison_verrou}
            </Banner>
          )}
          {contestation && (
            <Banner
              tone="error"
              actions={<AdminButton size="sm" onClick={() => setReponse('')}>Traiter la contestation…</AdminButton>}
            >
              <strong>Le professeur conteste ses heures :</strong> « {contestation.motif} ». Adaptez les saisies si besoin, puis répondez pour les remettre en revue.
            </Banner>
          )}
          {sf?.possible && (
            <Banner tone="warning" actions={<AdminButton size="sm" onClick={() => { setErreurForcer(null); setForcer(true); }}>Soumettre à sa place…</AdminButton>}>
              <strong>{formatHeures(sf.heures)} en brouillon, non soumises.</strong> Le professeur n'a pas soumis son mois. Vous pouvez le soumettre en l'état pour lancer la validation.
            </Banner>
          )}
          {sf && !sf.possible && sf.raison_impossible === 'aucune_heure' && (
            <p style={{ color: 'var(--c-text-2)', fontSize: 13 }}>Aucune heure encodée : rien à soumettre.</p>
          )}
          {d.resume?.alertes.filter((a) => !['depassement', 'brouillon_non_soumis'].includes(a.code)).map((a) => <Banner key={a.code} tone="warning">{a.libelle}</Banner>)}
          {nbDepassements > 0 && (
            <Banner
              tone="warning"
              actions={<AdminButton variant="secondary" size="sm" onClick={() => setLisser(null)}>Lisser le mois…</AdminButton>}
            >
              {nbDepassements} jour(s) dépassent le plafond de {formatEuros(d.periode.plafond_journalier_eur)}.
            </Banner>
          )}

          <NoteInterneMois professeurId={professeurId} annee={annee} mois={moisNum} note={d.notes_internes?.mois} onChange={detail.reload} />

          {d.lignes.some((t) => t.statut_validation !== 'brouillon') && (
            <VersionsMois professeurId={professeurId} annee={annee} mois={moisNum} rafraichir={d} />
          )}

          <div className="detail-grid">
            <div>
              <Table caption="Saisies du mois" minWidth="640px" cards>
                <thead><tr><Th>Date</Th><Th>Session / cours</Th><Th>Type</Th><Th>Heures</Th><Th>Montant</Th><Th>Statut</Th><Th srOnly>Actions</Th></tr></thead>
                <tbody>
                  {d.lignes.length === 0 && <Tr><Td colSpan={7}>Aucune saisie ce mois-ci.</Td></Tr>}
                  {d.lignes.map((t) => (
                    <Fragment key={t.id}>
                    <Tr fond={modifiees.has(t.id) ? 'var(--tone-warning-bg)' : undefined}>
                      <Td label="Date">{formatDateCourte(t.date_prestation)}</Td>
                      <Td label="Session / cours">{(t.session ? libelleSession(t.session) : 'Heures libres')}</Td>
                      <Td label="Type">{TYPES_ACTIVITE[t.type_activite]?.label || t.type_activite}</Td>
                      <Td label="Heures">{formatHeures(t.nombre_heures)}</Td>
                      <Td label="Montant">{formatEuros(t.montant_brut)}</Td>
                      <Td label="Statut">
                        <StatutBadge table={STATUTS_TIMESHEET} valeur={t.statut_validation} />
                        {modifiees.has(t.id) && <span title="Adaptée ou lissée" style={{ display: 'block', marginTop: 2, fontSize: 12 }}>✎ modifiée</span>}
                      </Td>
                      <Td label="Actions">
                        <span style={{ display: 'inline-flex', flexWrap: 'wrap', gap: 6 }}>
                          {t.can?.adapt && (
                            <>
                              <AdminButton variant="secondary" size="sm" onClick={() => setAdapter(t)}>Adapter</AdminButton>
                              <AdminButton variant="secondary" size="sm" onClick={() => setLisser(t)}>Lisser</AdminButton>
                            </>
                          )}
                          {(() => {
                            const note = d.notes_internes?.lignes?.[t.id];
                            const date = formatDateCourte(t.date_prestation);
                            return (
                              <button
                                type="button"
                                aria-label={`Note interne de la ligne du ${date} — ${note ? 'présente' : 'vide'}`}
                                title={note ? `Note interne : « ${note.texte.slice(0, 80)}${note.texte.length > 80 ? '…' : ''} »` : 'Ajouter une note interne'}
                                onClick={() => setNoteLigne(noteLigne === t.id ? null : t.id)}
                                style={{ minWidth: 36, minHeight: 36, padding: '2px 8px', borderRadius: 7, cursor: 'pointer', border: `1px solid ${note ? '#e0b64a' : 'var(--c-border)'}`, background: note ? '#fff7e0' : '#fff', opacity: note ? 1 : 0.6 }}
                              >
                                <span aria-hidden="true">🔒{note ? '●' : '✎'}</span>
                              </button>
                            );
                          })()}
                        </span>
                      </Td>
                    </Tr>
                    {noteLigne === t.id && !mobile && (
                      <Tr>
                        <Td colSpan={7} style={{ background: '#fffdf5' }}>
                          <NoteInterne
                            professeurId={professeurId}
                            annee={annee}
                            mois={moisNum}
                            timesheetId={t.id}
                            note={d.notes_internes?.lignes?.[t.id]}
                            libelle={`ligne du ${formatDateCourte(t.date_prestation)}`}
                            ouvertEnEdition
                            onChange={detail.reload}
                            onFerme={() => setNoteLigne(null)}
                          />
                        </Td>
                      </Tr>
                    )}
                    </Fragment>
                  ))}
                </tbody>
              </Table>

            </div>

            <aside className="detail-side" aria-label="Résumé du mois">
              <section style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-card)', padding: 16 }}>
                <h3 style={{ marginTop: 0 }}>Résumé</h3>
                <p style={{ margin: 0 }}><strong>{formatEuros(totaux.e)}</strong> · {formatHeures(totaux.h)}</p>
                <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--c-text-2)' }}>Plafond : {formatEuros(d.periode.plafond_journalier_eur)} par jour</p>
              </section>
              <section style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-card)', padding: 16 }}>
                <h3 style={{ marginTop: 0 }}>Calendrier</h3>
                <CalendrierMois annee={annee} mois={moisNum} jours={d.jours} plafond={d.periode.plafond_journalier_eur} />
              </section>
              <section style={{ border: '1px solid var(--c-border)', borderRadius: 8, background: 'var(--c-card)', padding: 16 }}>
              <h3 style={{ marginTop: 0 }}>Historique</h3>
              <FriseMois
                professeurId={professeurId}
                annee={annee}
                mois={moisNum}
                historique={d.historique}
                signature={d.signature}
                notes={d.notes_internes}
                lignes={d.lignes}
                pdfs={d.pdf.versions}
                texteAudit={texteAudit}
                rafraichir={d}
              />
              </section>
            </aside>
          </div>

          <div style={{ marginTop: ADMIN_SPACING.lg }}>
            <EmployeurMoisSection professeurId={professeurId} professeurNom={d.professeur.nom} anneeInitiale={annee} onChange={() => { detail.reload(); onChange?.(); }} />
          </div>

          {employeurModal && (
            <DefinirEmployeurModal
              annee={annee}
              mois={moisNum}
              cibles={[{ professeur_id: professeurId, professeur: d.professeur.nom, vue: d.employeur }]}
              onClose={() => setEmployeurModal(false)}
              onDone={({ message }) => { setEmployeurModal(false); recharger(message); }}
            />
          )}
          {confirmationPdf && (
            <AdminModal
              isOpen
              title="Générer la fiche PDF"
              size="sm"
              onClose={() => setConfirmationPdf(false)}
              footer={
                <>
                  <AdminButton variant="secondary" onClick={() => setConfirmationPdf(false)}>Annuler</AdminButton>
                  <AdminButton
                    loading={pdfEnCours}
                    onClick={async () => { await actionPdf(() => genererPdf(professeurId, annee, moisNum), 'PDF généré.'); setConfirmationPdf(false); }}
                  >
                    Générer
                  </AdminButton>
                </>
              }
            >
              <p style={{ marginTop: 0 }}>
                Fiche émise au nom de : <strong>{d.employeur?.employeur.nom}</strong> — l’entité est figée sur cette version et responsable de traitement des heures du mois.
              </p>
              <p style={{ fontSize: 13, color: 'var(--c-text-2)', marginBottom: 0 }}>Les saisies du mois passeront à « généré » et ne seront plus modifiables sans déverrouillage par un administrateur.</p>
            </AdminModal>
          )}
          {deverrouillage !== null && (
            <AdminModal
              isOpen
              title="Déverrouiller le mois"
              size="sm"
              onClose={() => setDeverrouillage(null)}
              footer={
                <>
                  <AdminButton variant="secondary" onClick={() => setDeverrouillage(null)}>Annuler</AdminButton>
                  <AdminButton loading={pdfEnCours} disabled={deverrouillage.trim().length < 3} onClick={confirmerDeverrouillage}>Déverrouiller</AdminButton>
                </>
              }
            >
              <p style={{ marginTop: 0 }}>Les saisies repassent en « confirmé » pour pouvoir être corrigées ; le PDF actuel est conservé et la prochaine génération crée une nouvelle version.</p>
              <AdminFormField label="Motif (obligatoire)" htmlFor="deverrouillage-motif">
                <AdminTextarea id="deverrouillage-motif" rows={2} value={deverrouillage} onChange={(e) => setDeverrouillage(e.target.value)} />
              </AdminFormField>
            </AdminModal>
          )}
          {brouillon !== null && (
            <AdminModal
              isOpen
              title="Remettre le mois en brouillon"
              size="sm"
              onClose={() => setBrouillon(null)}
              footer={
                <>
                  <AdminButton variant="secondary" onClick={() => setBrouillon(null)}>Annuler</AdminButton>
                  <AdminButton loading={envoiBrouillon} disabled={brouillon.trim().length < 3} onClick={envoyerBrouillon}>
                    Remettre le mois en brouillon
                  </AdminButton>
                </>
              }
            >
              {erreurBrouillon && <Banner tone="error" role="alert">{erreurBrouillon}</Banner>}
              <ul style={{ marginTop: 0, paddingLeft: 18 }}>
                <li>Tout le mois ({d.remise_brouillon.lignes} ligne(s)) repasse en brouillon, signatures comprises : le professeur pourra corriger puis soumettre son mois complet à nouveau.</li>
                <li>Les lignes déjà validées, signées ou incluses dans un PDF ne sont pas touchées.</li>
                <li>{d.professeur.nom} est prévenu(e) par notification et par email, avec votre motif.</li>
              </ul>
              <AdminFormField label="Motif (obligatoire)" htmlFor="brouillon-motif">
                <AdminTextarea id="brouillon-motif" rows={3} value={brouillon} onChange={(e) => setBrouillon(e.target.value)} />
              </AdminFormField>
            </AdminModal>
          )}
          {reponse !== null && (
            <AdminModal
              isOpen
              title="Traiter la contestation"
              size="sm"
              onClose={() => setReponse(null)}
              footer={
                <>
                  <AdminButton variant="secondary" onClick={() => setReponse(null)}>Annuler</AdminButton>
                  <AdminButton loading={envoiReponse} disabled={reponse.trim().length < 3} onClick={envoyerReponse}>Répondre et remettre en revue</AdminButton>
                </>
              }
            >
              {erreurReponse && <Banner tone="error" role="alert">{erreurReponse}</Banner>}
              <AdminFormField label="Réponse au professeur (obligatoire)" htmlFor="reponse-contestation">
                <AdminTextarea id="reponse-contestation" rows={3} value={reponse} onChange={(e) => setReponse(e.target.value)} />
              </AdminFormField>
            </AdminModal>
          )}
          {noteLigne !== null && mobile && (() => {
            const t = d.lignes.find((x) => x.id === noteLigne);
            return t ? (
              <AdminModal isOpen title={`Note interne de la ligne du ${formatDateCourte(t.date_prestation)}`} size="sm" onClose={() => setNoteLigne(null)}>
                <NoteInterne
                  professeurId={professeurId}
                  annee={annee}
                  mois={moisNum}
                  timesheetId={t.id}
                  note={d.notes_internes?.lignes?.[t.id]}
                  libelle={`ligne du ${formatDateCourte(t.date_prestation)}`}
                  ouvertEnEdition
                  onChange={detail.reload}
                  onFerme={() => setNoteLigne(null)}
                />
              </AdminModal>
            ) : null;
          })()}
          {forcer && (
            <SoumettreALaPlaceModal
              nom={d.professeur.nom}
              prenom={d.professeur.nom.split(' ')[0]}
              titreMois={new Date(annee, moisNum - 1, 1).toLocaleDateString('fr-BE', { month: 'long', year: 'numeric' })}
              etat={sf}
              envoi={envoiForcer}
              erreur={erreurForcer}
              onConfirmer={envoyerSoumissionForcee}
              onClose={() => setForcer(false)}
            />
          )}
          {adapter && <AdapterSaisieModal saisie={adapter} onClose={() => setAdapter(null)} onDone={recharger} />}
          {lisser !== undefined && (
            <LisserMoisModal professeurId={professeurId} annee={annee} mois={moisNum} saisie={lisser || undefined} onClose={() => setLisser(undefined)} onDone={recharger} />
          )}
          {valider && (
            <ValiderLotModal
              selection={soumises}
              toutesLesSaisies={d.lignes}
              annee={annee}
              mois={moisNum}
              plafond={d.periode.plafond_journalier_eur}
              onClose={() => setValider(false)}
              onDone={recharger}
            />
          )}
        </>
      )}
    </>
  );
}
