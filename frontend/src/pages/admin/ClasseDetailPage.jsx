import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../../components/AdminPageLayout';
import AdminButton from '../../components/AdminButton';
import AdminModal from '../../components/AdminModal';
import LinkButton from '../../components/ui/LinkButton';
import Banner from '../../components/ui/Banner';
import StatutBadge from '../../components/ui/StatutBadge';
import { Section } from '../../components/ui/Card';
import { LoadingBlock, ErrorBlock } from '../../components/ui/DataStates';
import ClasseSessionsTable from '../../components/classes/ClasseSessionsTable';
import SessionAdjustModal from '../../components/classes/SessionAdjustModal';
import ProfesseursClasseSection from '../../components/professeurs/ProfesseursClasseSection';
import RemplacerProfesseurModal from '../../components/professeurs/RemplacerProfesseurModal';
import { useProfesseursListe } from '../../hooks/useProfesseursClasses';
import { useClasse, useClasseSessions, modifierClasse, supprimerClasse } from '../../hooks/useClasses';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getStatus } from '../../api/errors';
import { STATUTS_CLASSE, STATUT_CLASSE_ARCHIVEE, estClasseArchivee, estSessionBarree } from '../../utils/statuts';
import { formatDate, formatDateCourte, libelleClasse } from '../../utils/dates';
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
  const titre = libelleClasse(c);
  const annulees = liste.filter((s) => estSessionBarree(s.statut));
  const alertes = liste.filter((s) => s.alerte_calendrier);
  const premiere = liste[0]?.date;
  const datesTriees = liste.map((s) => s.date).sort();
  const derniere = datesTriees[datesTriees.length - 1];
  const peutBis = liste.some((s) => s.can?.bis);

  function recharger() {
    classe.reload();
    sessions.reload();
  }

  function apresRemplacement(message, avertissements = []) {
    setRemplacement(null);
    toast.success(message);
    avertissements.forEach((a) => toast.error(a.message));
    recharger();
  }

  function apresAjustement(message) {
    setAjustement(null);
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
          Archiver la classe
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
          `${c.annee_scolaire?.libelle}, période ${c.periode?.numero} (jusqu'au ${formatDate(c.periode?.date_fin)})`,
          premiere ? `du ${formatDate(premiere)} au ${formatDate(derniere)}` : null,
          `${c.nb_sessions} session${c.nb_sessions > 1 ? 's' : ''} actives${annulees.length ? ` · ${annulees.length} annulée${annulees.length > 1 ? 's' : ''}` : ''}`,
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
                  {s.libelle.toLowerCase()}, {formatDateCourte(s.date)} — « {s.alerte_calendrier.libelle} ». Elle n'a pas été déplacée automatiquement.{' '}
                  {(s.can?.update || s.can?.cancel) && (
                    <AdminButton size="sm" variant="secondary" onClick={() => setAjustement({ session: s, mode: 'deplacer' })}>
                      Ajuster la {s.libelle.toLowerCase()}
                    </AdminButton>
                  )}
                </li>
              ))}
            </ul>
          </Banner>
        )}

        <ProfesseursClasseSection classe={c} titreClasse={titre} onChange={sessions.reload} />

        <Section
          title="Sessions"
          subtitle={`${liste.length} session${liste.length > 1 ? 's' : ''} (séances, bis et annulées comprises)`}
          bodyPadding={false}
          actions={
            peutBis && (
              <AdminButton size="sm" variant="secondary" onClick={() => setAjustement({ session: null, mode: 'bis' })}>
                ＋ Ajouter un bis
              </AdminButton>
            )
          }
        >
          <div
            id="legende-sessions"
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
            <span>🔢 Le numéro de séance (1 à 14) est fixe : une session annulée le garde, un bis porte le même numéro</span>
            <span>⚠ Date en conflit avec le calendrier scolaire</span>
          </div>
          {liste.length === 0 ? (
            <p style={{ padding: ADMIN_SPACING.xl, margin: 0 }}>Cette classe n'a aucune session.</p>
          ) : (
            <ClasseSessionsTable
              sessions={liste}
              onAjuster={(session, mode) => setAjustement({ session, mode })}
              onRemplacer={c.can?.update ? setRemplacement : undefined}
            />
          )}
        </Section>
      </AdminPageContent>

      {ajustement && (
        <SessionAdjustModal
          classe={c}
          sessions={liste}
          session={ajustement.session}
          modeInitial={ajustement.mode}
          onClose={() => setAjustement(null)}
          onDone={apresAjustement}
        />
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
            Les {liste.length} sessions de la classe seront supprimées avec elle. Cette action est définitive.
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
