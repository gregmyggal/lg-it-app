import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import AdminModal from '../../components/AdminModal';
import LinkButton from '../../components/ui/LinkButton';
import Banner from '../../components/ui/Banner';
import StatutBadge from '../../components/ui/StatutBadge';
import { Section } from '../../components/ui/Card';
import { LoadingBlock, ErrorBlock } from '../../components/ui/DataStates';
import { formatDuree } from '../../utils/format';
import ClasseSessionsTable from '../../components/classes/ClasseSessionsTable';
import SessionAdjustModal from '../../components/classes/SessionAdjustModal';
import ProfesseursClasseSection from '../../components/professeurs/ProfesseursClasseSection';
import RemplacerProfesseurModal from '../../components/professeurs/RemplacerProfesseurModal';
import { useProfesseursListe } from '../../hooks/useProfesseursClasses';
import { useLiensCours } from '../../hooks/useLiens';
import LienModifierDates from '../../components/annees/LienModifierDates';
import { useAnneesScolaires } from '../../hooks/useAnneesScolaires';
import { effacerBrouillon, lireBrouillon } from '../../utils/annees';
import PeriodeBadge from '../../components/classes/PeriodeBadge';
import { AjouterPeriodeModal, ChangerCoursModal, SupprimerPeriodeModal, HistoriqueCoursModal } from '../../components/classes/PeriodeModals';
import { useClasse, useClasseSessions, modifierClasse, supprimerClasse } from '../../hooks/useClasses';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getStatus } from '../../api/errors';
import { STATUTS_CLASSE, STATUT_CLASSE_ARCHIVEE, estClasseArchivee, estSessionBarree } from '../../utils/statuts';
import { formatDate, formatDateCourte, jourDeClasseApres, libelleClasse } from '../../utils/dates';
import { libelleSessionPhrase } from '../../utils/classes';
import { ADMIN_COLORS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';

/** Écran « Détail d'une classe » : informations, professeurs, sessions, ajustements et remplacements (mock-up 03). */
export default function ClasseDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const classe = useClasse(id);
  const sessions = useClasseSessions(id);
  const professeursListe = useProfesseursListe();
  const [remplacement, setRemplacement] = useState(null); // session à remplacer
  const [ajustement, setAjustement] = useState(null); // { session, mode }
  const [suppression, setSuppression] = useState(null); // { etape: 'confirmer'|'refus', message }
  const [enCours, setEnCours] = useState(false);
  const [modalePeriode, setModalePeriode] = useState(null); // { type: 'ajout'|'cours'|'suppression', numero?, periode? }
  const annees = useAnneesScolaires();
  const [params] = useSearchParams();
  const location = useLocation();

  // Retour de « Modifier les dates » : message de confirmation et réouverture de la modale « Ajouter la période » avec la saisie conservée.
  useEffect(() => {
    if (location.state?.datesMisesAJour) toast.success(location.state.datesMisesAJour);
    if (!params.get('restaurer_periode')) return;
    const cle = `periode-${id}`;
    const brouillon = lireBrouillon(cle);
    effacerBrouillon(cle);
    if (brouillon) setModalePeriode({ type: 'ajout', numero: brouillon.numero, dateConseillee: brouillon.date, coursIdInitial: brouillon.coursId });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const entete = (titre, actions) => (
    <AdminPageHeader
      icon="🏫"
      title={titre}
      breadcrumb={
        <>
          Scolarité › <Link to="/admin/classes">Classes</Link> › {titre}
        </>
      }
      action={actions}
    />
  );

  if (!classe.error && !sessions.error && (!classe.data || !sessions.data)) {
    return (
      <>
        {entete('Classe')}
        <AdminPageContent>
          <LoadingBlock message="Chargement de la classe…" />
        </AdminPageContent>
      </>
    );
  }
  if (classe.error || sessions.error) {
    return (
      <>
        {entete('Classe')}
        <AdminPageContent>
          <ErrorBlock
            message="Impossible de charger cette classe. Vérifiez votre connexion puis réessayez."
            onRetry={() => {
              classe.reload();
              sessions.reload();
            }}
          />
        </AdminPageContent>
      </>
    );
  }

  const c = classe.data;
  const liste = sessions.data;
  const anneeClasse = (annees.data || []).find((a) => a.id === c.annee_scolaire_id) || null;
  const titre = libelleClasse(c);
  const annulees = liste.filter((s) => estSessionBarree(s.statut));
  const alertes = liste.filter((s) => s.alerte_calendrier);
  const premiere = liste[0]?.date;
  const datesTriees = liste.map((s) => s.date).sort();
  const derniere = datesTriees[datesTriees.length - 1];

  function recharger() {
    classe.reload();
    sessions.reload();
  }

  function apresRemplacement(message, avertissements = []) {
    setRemplacement(null);
    toast.success(message);
    avertissements.forEach((a) => toast.warning(a.message));
    recharger();
  }

  function apresAjustement(message, avertissements = []) {
    setAjustement(null);
    toast.success(message);
    avertissements.forEach((a) => toast.warning(typeof a === 'string' ? a : a.message));
    recharger();
  }

  function ouvrirAjout(numero) {
    const autre = (c.periodes || []).find((p) => p.numero !== numero);
    const dateConseillee =
      numero === 2 && autre?.date_derniere_session
        ? jourDeClasseApres(autre.date_derniere_session, null, c.jour_semaine)
        : '';
    setModalePeriode({ type: 'ajout', numero, dateConseillee });
  }

  function apresPeriode(message) {
    setModalePeriode(null);
    toast.success(message);
    recharger();
  }

  async function archiver() {
    setEnCours(true);
    try {
      await modifierClasse(c.id, { statut: STATUT_CLASSE_ARCHIVEE });
      toast.success('Classe archivée. Elle reste consultable avec tout son historique.');
      setSuppression(null);
      recharger();
    } catch (err) {
      toast.error(getErrorMessage(err, "La classe n'a pas pu être archivée."));
    } finally {
      setEnCours(false);
    }
  }

  async function supprimer() {
    setEnCours(true);
    try {
      await supprimerClasse(c.id);
      toast.success(`Classe « ${titre} » supprimée.`);
      navigate('/admin/classes');
    } catch (err) {
      if (getStatus(err) === 409) {
        setSuppression({ etape: 'refus', message: getErrorMessage(err) });
      } else {
        setSuppression(null);
        toast.error(getErrorMessage(err, "La classe n'a pas pu être supprimée."));
      }
    } finally {
      setEnCours(false);
    }
  }

  const actionsEntete = (
    <>
      <StatutBadge table={STATUTS_CLASSE} valeur={c.statut} />
      <LinkButton to={`/admin/calendrier?classe_id=${c.id}&annee_scolaire_id=${c.annee_scolaire_id}`} size="sm">
        Voir dans le calendrier
      </LinkButton>
      {c.can?.update && !estClasseArchivee(c.statut) && (
        <AdminButton variant="secondary" size="sm" onClick={archiver} loading={enCours}>
          Archiver la classe (P1 et P2)
        </AdminButton>
      )}
      {c.can?.delete && (
        <AdminButton variant="secondary" size="sm" onClick={() => setSuppression({ etape: 'confirmer' })} style={{ color: ADMIN_TONES.error.fg }}>
          Supprimer la classe
        </AdminButton>
      )}
    </>
  );

  return (
    <>
      <AdminPageHeader
        icon="🏫"
        title={titre}
        breadcrumb={
          <>
            Scolarité › <Link to="/admin/classes">Classes</Link> › {titre}
          </>
        }
        description={[
          c.lieu,
          c.annee_scolaire?.libelle,
          premiere ? `du ${formatDate(premiere)} au ${formatDate(derniere)}` : null,
          `${c.nb_sessions} session${c.nb_sessions > 1 ? 's' : ''} actives${annulees.length ? ` · ${annulees.length} annulée${annulees.length > 1 ? 's' : ''}` : ''}`,
          c.duree_seance ? `Séance ${formatDuree(c.duree_seance)}` : null,
        ]
          .filter(Boolean)
          .join(' · ')}
        action={actionsEntete}
      />

      <AdminPageContent>
        {alertes.length > 0 && (
          <Banner tone="warning" role="alert">
            <strong>
              {alertes.length === 1
                ? '1 session tombe sur une date du calendrier scolaire ajoutée après la création :'
                : `${alertes.length} sessions tombent sur des dates du calendrier scolaire ajoutées après la création :`}
            </strong>
            <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px' }}>
              {alertes.map((s) => (
                <li key={s.id}>
                  {libelleSessionPhrase(s)}, {formatDateCourte(s.date)} — « {s.alerte_calendrier.libelle} ». Elle n'a pas été déplacée automatiquement.{' '}
                  {(s.can?.update || s.can?.cancel) && (
                    <AdminButton size="sm" variant="secondary" onClick={() => setAjustement({ session: s, mode: 'deplacer' })}>
                      Ajuster la {libelleSessionPhrase(s)}
                    </AdminButton>
                  )}
                </li>
              ))}
            </ul>
          </Banner>
        )}

        {c.alerte_periode_2 && (
          <Banner
            tone="warning"
            role="status"
            actions={
              c.can?.update && (
                <AdminButton size="sm" onClick={() => ouvrirAjout(2)}>
                  Ajouter la période 2
                </AdminButton>
              )
            }
          >
            <strong>⏰ Période 2 à planifier :</strong>{' '}
            {c.alerte_periode_2.message || 'la période 2 est à planifier'} — la période 1 se termine le {formatDate(c.alerte_periode_2.date_fin_periode_1)}
            {Number.isFinite(c.alerte_periode_2.jours_restants)
              ? c.alerte_periode_2.jours_restants >= 0
                ? ` (dans ${c.alerte_periode_2.jours_restants} jour${c.alerte_periode_2.jours_restants > 1 ? 's' : ''})`
                : ' (terminée)'
              : ''}{' '}
            et la classe n'a pas de période 2.
          </Banner>
        )}

        <ProfesseursClasseSection classe={c} titreClasse={titre} onChange={sessions.reload} />

        {[1, 2].map((n) => {
          const p = (c.periodes || []).find((x) => x.numero === n);
          if (!p) {
            if (!c.can?.update || estClasseArchivee(c.statut)) return null;
            return (
              <PeriodeAbsente key={n} numero={n} dejaPresente={(c.periodes || [])[0]} onAjouter={() => ouvrirAjout(n)} />
            );
          }
          return (
            <PeriodeBloc
              key={n}
              periode={p}
              annee={anneeClasse}
              classeId={c.id}
              sessions={liste.filter((s) => (s.periode_numero || 1) === n)}
              peutModifier={Boolean(c.can?.update) && !estClasseArchivee(c.statut)}
              peutSupprimer={(c.periodes || []).length > 1}
              onAjuster={(session, mode) => setAjustement({ session, mode: mode ?? undefined, periodeNumero: n })}
              onRemplacer={c.can?.update ? setRemplacement : undefined}
              onChangerCours={() => setModalePeriode({ type: 'cours', periode: p })}
              onSupprimer={() => setModalePeriode({ type: 'suppression', periode: p })}
              onHistorique={() => setModalePeriode({ type: 'historique', periode: p })}
            />
          );
        })}
      </AdminPageContent>

      {ajustement && (
        <SessionAdjustModal
          classe={c}
          sessions={liste}
          session={ajustement.session}
          modeInitial={ajustement.mode}
          periodeInitiale={ajustement.periodeNumero}
          onClose={() => setAjustement(null)}
          onDone={apresAjustement}
        />
      )}

      {modalePeriode?.type === 'ajout' && (
        <AjouterPeriodeModal
          classe={c}
          numero={modalePeriode.numero}
          dateConseillee={modalePeriode.dateConseillee}
          coursIdInitial={modalePeriode.coursIdInitial}
          onClose={() => setModalePeriode(null)}
          onDone={apresPeriode}
        />
      )}
      {modalePeriode?.type === 'cours' && (
        <ChangerCoursModal classe={c} periode={modalePeriode.periode} onClose={() => setModalePeriode(null)} onDone={apresPeriode} />
      )}
      {modalePeriode?.type === 'historique' && (
        <HistoriqueCoursModal classe={c} periode={modalePeriode.periode} onClose={() => setModalePeriode(null)} />
      )}
      {modalePeriode?.type === 'suppression' && (
        <SupprimerPeriodeModal classe={c} periode={modalePeriode.periode} onClose={() => setModalePeriode(null)} onDone={apresPeriode} />
      )}

      {remplacement && (
        <RemplacerProfesseurModal
          session={remplacement}
          professeurs={(professeursListe.data || []).filter((p) => p.statut !== 'inactif').map((p) => ({ value: String(p.id), label: p.nom }))}
          onClose={() => setRemplacement(null)}
          onDone={apresRemplacement}
        />
      )}

      {suppression?.etape === 'confirmer' && (
        <AdminModal
          isOpen
          title={`Supprimer la classe « ${titre} » ?`}
          size="sm"
          onClose={() => setSuppression(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setSuppression(null)}>
                Conserver la classe
              </AdminButton>
              <AdminButton variant="danger" onClick={supprimer} loading={enCours}>
                Supprimer la classe
              </AdminButton>
            </>
          }
        >
          <p style={{ margin: 0 }}>
            Les {liste.length} sessions de la classe (toutes périodes) seront supprimées avec elle. Cette action est définitive.
          </p>
        </AdminModal>
      )}

      {suppression?.etape === 'refus' && (
        <AdminModal
          isOpen
          title="Impossible de supprimer cette classe"
          size="sm"
          onClose={() => setSuppression(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setSuppression(null)}>
                Fermer
              </AdminButton>
              {c.can?.update && !estClasseArchivee(c.statut) && (
                <AdminButton onClick={archiver} loading={enCours}>
                  Archiver la classe
                </AdminButton>
              )}
            </>
          }
        >
          <Banner tone="error">
            <strong>Suppression refusée.</strong> {suppression.message}
          </Banner>
          <p>Vous pouvez à la place :</p>
          <ul style={{ margin: `0 0 0 ${ADMIN_SPACING.lg}` }}>
            <li>
              <strong>Archiver la classe</strong> : elle disparaît des listes actives mais garde tout son historique.
            </li>
            <li>
              <strong>Annuler des sessions</strong> à venir, une par une.
            </li>
          </ul>
        </AdminModal>
      )}
    </>
  );
}

/** Carte pointillée : la classe n'a pas encore cette période (mock-up CLS-02/02). */
function PeriodeAbsente({ numero, dejaPresente, onAjouter }) {
  return (
    <Section title={`Cette classe n'a pas encore de période ${numero}`} headingLevel={2} style={{ borderStyle: 'dashed' }}>
      <p style={{ marginTop: 0 }}>
        {numero === 1
          ? `Elle démarre en période 2. Vous pouvez ajouter la période 1 (cours + date) : les séances P1 devront précéder celles de la P2 sur le même créneau.`
          : `Choisissez le cours et la date de démarrage : 14 séances seront ajoutées avec les mêmes jour, horaire, lieu et professeurs${dejaPresente ? '' : ''}.`}
      </p>
      <AdminButton onClick={onAjouter}>＋ Ajouter la période {numero}</AdminButton>
    </Section>
  );
}

/** Bloc d'une période : bandeau (cours, dates, hors période), actions de période, tableau des séances. */
function PeriodeBloc({ periode, annee, classeId, sessions, peutModifier, peutSupprimer, onAjuster, onRemplacer, onChangerCours, onSupprimer, onHistorique }) {
  const liens = useLiensCours(periode.cours_id);
  const annulee = periode.statut === 'annulee';
  const peutBis = sessions.some((s) => s.can?.bis);
  const titre = `Période ${periode.numero} · ${periode.cours?.titre || 'Cours'}`;
  const dateFin = periode.periode?.date_fin;
  return (
    <Section
      title={
        <span style={{ display: 'inline-flex', gap: ADMIN_SPACING.sm, alignItems: 'center', flexWrap: 'wrap' }}>
          <PeriodeBadge numero={periode.numero} /> {titre}
        </span>
      }
      subtitle={[
        periode.date_premiere_session
          ? `${formatDate(periode.date_premiere_session)} → ${periode.date_derniere_session ? formatDate(periode.date_derniere_session) : '…'}`
          : null,
        dateFin ? `fin de période ${formatDate(dateFin)}` : null,
        `${periode.nb_sessions} séance${periode.nb_sessions > 1 ? 's' : ''}`,
      ]
        .filter(Boolean)
        .join(' · ')}
      bodyPadding={false}
      headingLevel={2}
      actions={
        <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center' }}>
          {periode.nb_hors_periode > 0 && (
            <StatutBadge label={`⚠ ${periode.nb_hors_periode} séance${periode.nb_hors_periode > 1 ? 's' : ''} hors période`} tone="warning" />
          )}
          {annulee && <StatutBadge label="Période annulée" tone="neutral" />}
          {periode.nb_changements_cours > 0 && (
            <AdminButton size="sm" variant="secondary" onClick={onHistorique}>
              {periode.nb_changements_cours} changement{periode.nb_changements_cours > 1 ? 's' : ''} de cours
            </AdminButton>
          )}
          {peutModifier && !annulee && (
            <AdminButton size="sm" variant="secondary" onClick={onChangerCours}>
              Changer le cours
            </AdminButton>
          )}
          {periode.cours_id && (
            <LinkButton to={`/admin/cours/${periode.cours_id}/liens`} size="sm">
              Liens du cours
            </LinkButton>
          )}
          {peutModifier && !annulee && peutBis && (
            <AdminButton size="sm" variant="secondary" onClick={() => onAjuster(null, 'bis')}>
              ＋ Séance / bis
            </AdminButton>
          )}
          {peutModifier && !annulee && peutSupprimer && (
            <AdminButton size="sm" variant="secondary" onClick={onSupprimer} style={{ color: ADMIN_TONES.error.fg }}>
              Supprimer / annuler la période
            </AdminButton>
          )}
        </div>
      }
    >
      {periode.periode?.date_debut && periode.periode?.date_fin && (
        <p style={{ margin: 0, padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.xl} 0`, fontSize: '13px', color: ADMIN_COLORS.textSecondary }}>
          Bornes : {formatDate(periode.periode.date_debut)} → {formatDate(periode.periode.date_fin)}
          {annee?.can?.update && annee.statut !== 'archivee' && (
            <>
              {' · '}
              <LienModifierDates annee={annee} numero={periode.numero} retour={`/admin/classes/${classeId}`} />
            </>
          )}
          <br />
          Ces bornes viennent de l'année scolaire {annee?.libelle || ''} et valent pour toutes les classes de l'année.
        </p>
      )}
      {annulee && periode.motif_annulation && (
        <div style={{ padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.xl}` }}>
          <Banner tone="warning">
            <strong>Période annulée.</strong> Motif : {periode.motif_annulation}
          </Banner>
        </div>
      )}
      <div
        id={`legende-sessions-${periode.numero}`}
        style={{
          display: 'flex',
          flexWrap: 'wrap',
          gap: `${ADMIN_SPACING.xs} ${ADMIN_SPACING.xl}`,
          padding: `${ADMIN_SPACING.md} ${ADMIN_SPACING.xl}`,
          fontSize: '13px',
          color: ADMIN_COLORS.textSecondary,
          borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        }}
      >
        <span>🔒 Session passée ou terminée : elle ne peut plus être déplacée ni annulée</span>
        <span>🔢 Le numéro de séance (1 à 14) est fixe dans la période : une session annulée le garde, un bis porte le même numéro</span>
        <span>⚠ Date en conflit avec le calendrier scolaire ou hors période</span>
      </div>
      {sessions.length === 0 ? (
        <p style={{ padding: ADMIN_SPACING.xl, margin: 0 }}>Cette période n'a aucune session.</p>
      ) : (
        <ClasseSessionsTable
          sessions={sessions}
          caption={`Sessions de la période ${periode.numero}`}
          legendeId={`legende-sessions-${periode.numero}`}
          liens={liens.data?.data}
          onAjuster={onAjuster}
          onRemplacer={onRemplacer}
        />
      )}
    </Section>
  );
}
