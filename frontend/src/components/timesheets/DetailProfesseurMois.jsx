import { useMemo, useState } from 'react';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import StatutBadge from '../ui/StatutBadge';
import { Table, Th, Td, Tr } from '../ui/Table';
import { LoadingBlock, ErrorBlock } from '../ui/DataStates';
import AdapterSaisieModal from './AdapterSaisieModal';
import LisserMoisModal from './LisserMoisModal';
import ValiderLotModal from './ValiderLotModal';
import AdminModal from '../AdminModal';
import { AdminFormField, AdminTextarea } from '../AdminFormField';
import { apercuPdf, deverrouillerMois, genererPdf, telechargerPdf, traiterContestation, useDetailMois } from '../../hooks/useTimesheets';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import { useAuth } from '../../auth/AuthContext';
import { enregistrerBlob, ouvrirPdf } from '../../utils/telechargement';
import { STATUTS_MOIS_PROF, STATUTS_TIMESHEET, TYPES_ACTIVITE } from '../../utils/statuts';
import { formatDateCourte, formatDateHeure } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';
import { libelleSession } from '../../utils/classes';

const LIBELLE_CHAMP = { nombre_heures: 'Heures', date_prestation: 'Date', type_activite: 'Type' };

function texteAudit(h) {
  if (h.action === 'contestation') return 'Contestation du professeur';
  if (h.action === 'reponse_contestation') return 'Réponse de la direction à la contestation';
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
          return (
            <div
              key={date}
              title={j ? `${formatEuros(j.montant)}${j.depasse ? ` — au-delà de ${formatEuros(plafond)}` : ''}` : undefined}
              style={{
                border: `1px solid ${j?.depasse ? ADMIN_COLORS.error : ADMIN_COLORS.border}`,
                background: j?.depasse ? 'var(--tone-error-bg)' : j ? 'var(--tone-success-bg)' : 'var(--c-card)',
                borderRadius: 5, padding: 3, minHeight: 44,
              }}
            >
              {n}
              {j && <div style={{ fontWeight: 600 }}>{formatEuros(j.montant)}{j.depasse ? ' ⚠' : ''}</div>}
            </div>
          );
        })}
      </div>
      <p style={{ fontSize: 12, color: 'var(--c-text-2)' }}>Rouge : au-delà de {formatEuros(plafond)} par jour.</p>
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
  const [envoiReponse, setEnvoiReponse] = useState(false);
  const [erreurReponse, setErreurReponse] = useState(null);

  const d = detail.data;
  const soumises = useMemo(() => (d?.lignes || []).filter((t) => t.statut_validation === 'soumis' && t.can?.validate), [d]);
  const modifiees = useMemo(() => new Set((d?.historique || []).map((h) => h.timesheet_id)), [d]);
  const nbDepassements = d?.jours.filter((j) => j.depasse).length || 0;
  const totaux = useMemo(() => (d?.lignes || []).reduce((a, t) => ({ h: a.h + Number(t.nombre_heures), e: a.e + (Number(t.montant_brut) || 0) }), { h: 0, e: 0 }), [d]);

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
            </div>
            <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap' }}>
              <AdminButton onClick={() => setValider(true)} disabled={soumises.length === 0}>Valider le mois ({soumises.length})</AdminButton>
              <AdminButton variant="secondary" disabled={pdfEnCours || d.lignes.length === 0} onClick={() => actionPdf(() => apercuPdf(professeurId, annee, moisNum).then(ouvrirPdf))}>Aperçu PDF</AdminButton>
              <AdminButton
                disabled={pdfEnCours || d.pdf.bloquants.length > 0}
                title={d.pdf.bloquants.join(' · ') || undefined}
                onClick={() => actionPdf(() => genererPdf(professeurId, annee, moisNum), 'PDF généré.')}
              >
                Générer le PDF
              </AdminButton>
              {dernierPdf && (
                <AdminButton variant="secondary" disabled={pdfEnCours} onClick={() => actionPdf(() => telecharger(dernierPdf))}>Télécharger (v{dernierPdf.version})</AdminButton>
              )}
              {user?.role === 'admin' && d.resume?.statut_mois === 'genere' && (
                <AdminButton variant="secondary" disabled={pdfEnCours} onClick={() => setDeverrouillage('')}>Déverrouiller…</AdminButton>
              )}
            </div>
          </div>
          {d.pdf.bloquants.length > 0 && d.lignes.length > 0 && (
            <p style={{ fontSize: 13, color: 'var(--tone-warning-fg)', margin: '0 0 12px' }}>PDF : {d.pdf.bloquants.join(' · ')}.</p>
          )}

          {contestation && (
            <Banner
              tone="error"
              actions={<AdminButton size="sm" onClick={() => setReponse('')}>Traiter la contestation…</AdminButton>}
            >
              <strong>Le professeur conteste ses heures :</strong> « {contestation.motif} ». Adaptez les saisies si besoin, puis répondez pour les remettre en revue.
            </Banner>
          )}
          {d.resume?.alertes.filter((a) => a.code !== 'depassement').map((a) => <Banner key={a.code} tone="warning">{a.libelle}</Banner>)}
          {nbDepassements > 0 && (
            <Banner
              tone="warning"
              actions={<AdminButton variant="secondary" size="sm" onClick={() => setLisser(null)}>Lisser le mois…</AdminButton>}
            >
              {nbDepassements} jour(s) dépassent le plafond de {formatEuros(d.periode.plafond_journalier_eur)}.
            </Banner>
          )}

          <div className="detail-grid">
            <div>
              <Table caption="Saisies du mois" minWidth="640px">
                <thead><tr><Th>Date</Th><Th>Session / cours</Th><Th>Type</Th><Th>Heures</Th><Th>Montant</Th><Th>Statut</Th><Th srOnly>Actions</Th></tr></thead>
                <tbody>
                  {d.lignes.length === 0 && <Tr><Td colSpan={7}>Aucune saisie ce mois-ci.</Td></Tr>}
                  {d.lignes.map((t) => (
                    <Tr key={t.id} fond={modifiees.has(t.id) ? 'var(--tone-warning-bg)' : undefined}>
                      <Td>{formatDateCourte(t.date_prestation)}</Td>
                      <Td>{(t.session ? libelleSession(t.session) : 'Heures libres')}</Td>
                      <Td>{TYPES_ACTIVITE[t.type_activite]?.label || t.type_activite}</Td>
                      <Td>{formatHeures(t.nombre_heures)}</Td>
                      <Td>{formatEuros(t.montant_brut)}</Td>
                      <Td>
                        <StatutBadge table={STATUTS_TIMESHEET} valeur={t.statut_validation} />
                        {modifiees.has(t.id) && <span title="Adaptée ou lissée" style={{ display: 'block', marginTop: 2, fontSize: 12 }}>✎ modifiée</span>}
                      </Td>
                      <Td>
                        {t.can?.adapt && (
                          <span style={{ display: 'inline-flex', flexWrap: 'wrap', gap: 6 }}>
                            <AdminButton variant="secondary" size="sm" onClick={() => setAdapter(t)}>Adapter</AdminButton>
                            <AdminButton variant="secondary" size="sm" onClick={() => setLisser(t)}>Lisser</AdminButton>
                          </span>
                        )}
                      </Td>
                    </Tr>
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
              {d.historique.length === 0 ? (
                <p style={{ color: 'var(--c-text-2)' }}>Aucune adaptation ce mois-ci.</p>
              ) : (
                <ul style={{ paddingLeft: 18, fontSize: 13 }}>
                  {d.historique.map((h) => (
                    <li key={h.id}>
                      {formatDateHeure(h.created_at)} — {h.auteur || 'Système'} · saisie du {formatDateCourte(h.date_prestation)} : {texteAudit(h)} — « {h.motif} »
                    </li>
                  ))}
                </ul>
              )}
              </section>
            </aside>
          </div>

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
