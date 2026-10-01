import { useEffect, useState } from 'react';
import AdminButton from '../AdminButton';
import AdminModal from '../AdminModal';
import { AdminSelect } from '../AdminFormField';
import { Section } from '../ui/Card';
import Banner from '../ui/Banner';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../ui/DataStates';
import LienLigne from './LienLigne';
import LienFormModal from './LienFormModal';
import { archiverLien, ordonnerLiens, reprendreRessources, restaurerVersion, useLiensCours } from '../../hooks/useLiens';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage } from '../../api/errors';
import { libelleCreneau } from '../../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const SEANCES = Array.from({ length: 14 }, (_, i) => i + 1);
const DUREE_ANNULATION_MS = 10000;

/**
 * Contenu de l'écran « Liens du cours » (mock-up 01), partagé par l'admin/directeur et les professeurs : liens généraux,
 * onglets des séances 1 à 14 (+ « Hors programme »), ajout / modification / archivage / réordonnancement selon `can`,
 * lecture seule expliquée pour un professeur sans classe active, reprise des anciennes ressources.
 * Supprimer = archiver : « Annuler » reste proposé 10 s, puis l'historique permet de restaurer pendant 6 mois.
 *
 * @param {object} props
 * @param {number|string} props.coursId
 * @param {(requete: object) => void} [props.onChargé] reçoit la réponse de l'API (titre du cours, droits…)
 */
export default function LiensDuCours({ coursId, onChargé }) {
  const toast = useToast();
  const liens = useLiensCours(coursId);
  const [seance, setSeance] = useState(1);
  const [classeId, setClasseId] = useState('');
  const [formulaire, setFormulaire] = useState(null); // { lien?, seanceInitiale }
  const [archivage, setArchivage] = useState(null);
  const [annulable, setAnnulable] = useState(null); // { historiqueId, titre }
  const [occupe, setOccupe] = useState(false);

  useEffect(() => {
    if (liens.data && onChargé) onChargé(liens.data);
  }, [liens.data]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!annulable) return undefined;
    const t = setTimeout(() => setAnnulable(null), DUREE_ANNULATION_MS);
    return () => clearTimeout(t);
  }, [annulable]);

  if (liens.loading && !liens.data) return <LoadingBlock message="Chargement des liens du cours…" lignes={4} />;
  if (liens.error) {
    return <ErrorBlock message="Impossible de charger les liens du cours. Vérifiez votre connexion puis réessayez. Vos liens n'ont pas été modifiés." onRetry={liens.reload} />;
  }

  const { data: tous, classes, cours, peut_modifier: peutModifier, anciennes_ressources: anciennes } = liens.data;
  const nbClasses = classes.length;
  const generaux = tous.filter((l) => l.seance_numero === null);
  const parSeance = (n) => tous.filter((l) => l.seance_numero === n);
  const horsProgramme = tous.filter((l) => l.hors_programme);
  const classeChoisie = classes.find((c) => String(c.id) === classeId) || classes[0];
  const seanceCourante = classeChoisie?.seance_courante?.seance_numero ?? null;

  async function agir(fn, succes) {
    setOccupe(true);
    try {
      await fn();
      if (succes) toast.success(succes);
      liens.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, 'Action impossible.'));
    } finally {
      setOccupe(false);
    }
  }

  function deplacer(lien, delta, portee) {
    const ids = portee.map((l) => l.id);
    const i = ids.indexOf(lien.id);
    [ids[i], ids[i + delta]] = [ids[i + delta], ids[i]];
    agir(() => ordonnerLiens(coursId, lien.seance_numero, ids), `Ordre enregistré. « ${lien.titre} » a été ${delta < 0 ? 'monté' : 'descendu'}.`);
  }

  async function archiver() {
    const lien = archivage;
    setOccupe(true);
    try {
      const r = await archiverLien(lien.id);
      setArchivage(null);
      setAnnulable({ historiqueId: r.historique_id, titre: lien.titre });
      liens.reload();
    } catch (err) {
      toast.error(getErrorMessage(err, "Le lien n'a pas pu être archivé."));
    } finally {
      setOccupe(false);
    }
  }

  const liste = (portee, seanceInitiale) =>
    portee.length === 0 ? (
      <p style={{ color: ADMIN_COLORS.textSecondary, margin: 0 }}>
        {seanceInitiale === null ? 'Aucun lien général.' : `Aucun lien pour la séance ${seanceInitiale}.`}{' '}
        {peutModifier && (
          <AdminButton size="sm" variant="secondary" onClick={() => setFormulaire({ seanceInitiale })}>
            {seanceInitiale === null ? 'Ajouter un lien général' : 'Ajouter un lien à cette séance'}
          </AdminButton>
        )}
      </p>
    ) : (
      <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.md }}>
        {portee.map((l, i) => (
          <LienLigne
            key={l.id}
            lien={l}
            premier={i === 0}
            dernier={i === portee.length - 1}
            occupe={occupe}
            onModifier={(x) => setFormulaire({ lien: x })}
            onArchiver={setArchivage}
            onDeplacer={(x, delta) => deplacer(x, delta, portee)}
          />
        ))}
      </ul>
    );

  return (
    <>
      {!peutModifier && (
        <Banner tone="warning">
          <strong>🔒 Vous n'enseignez pas ce cours.</strong> Seuls les professeurs ayant une classe active de {cours.titre}, la direction et l'administration peuvent modifier ses liens. Vous
          pouvez les consulter : demandez à la direction de vous assigner à une classe pour les adapter.
        </Banner>
      )}
      {peutModifier && nbClasses > 0 && (
        <Banner tone="info">
          Ces liens sont visibles par toutes les classes de ce cours ({classes.map((c) => `${cours.titre} — ${libelleCreneau(c)}`).join(', ')}) et par les élèves via le code de partage. Chaque
          modification est enregistrée à votre nom et peut être annulée pendant 6 mois.
        </Banner>
      )}
      {anciennes > 0 && peutModifier && (
        <Banner tone="warning">
          <strong>
            {anciennes} ancienne{anciennes > 1 ? 's' : ''} ressource{anciennes > 1 ? 's' : ''} n'{anciennes > 1 ? 'ont' : 'a'} pas encore été reprise{anciennes > 1 ? 's' : ''}.
          </strong>{' '}
          Elles restent visibles des élèves en attendant.{' '}
          <AdminButton size="sm" variant="secondary" disabled={occupe} onClick={() => agir(() => reprendreRessources(coursId), 'Anciennes ressources reprises comme liens généraux.')}>
            Reprendre comme liens généraux
          </AdminButton>
        </Banner>
      )}
      {annulable && (
        <Banner tone="success" role="status">
          Lien « {annulable.titre} » archivé pour toutes les classes du cours. Il reste restaurable depuis l'historique pendant 6 mois.{' '}
          <AdminButton
            size="sm"
            variant="secondary"
            onClick={() => {
              const id = annulable.historiqueId;
              setAnnulable(null);
              agir(() => restaurerVersion(id), 'Archivage annulé : le lien est de nouveau visible.');
            }}
          >
            Annuler
          </AdminButton>
        </Banner>
      )}

      {tous.length === 0 ? (
        <EmptyBlock
          icon="🔗"
          title={`Aucun lien pour ${cours.titre}`}
          actions={peutModifier && <AdminButton onClick={() => setFormulaire({ seanceInitiale: null })}>Ajouter le premier lien</AdminButton>}
        >
          Ajoutez un premier lien général (valable pour toutes les séances) ou un lien propre à une séance. Il sera visible par {nbClasses > 1 ? `les ${nbClasses} classes` : 'la classe'} du cours et par les élèves.
        </EmptyBlock>
      ) : (
        <>
          <Section
            title="Liens généraux"
            subtitle="Valables pour toutes les séances du cours."
            actions={peutModifier && <AdminButton size="sm" onClick={() => setFormulaire({ seanceInitiale: null })}>＋ Ajouter un lien général</AdminButton>}
          >
            {liste(generaux, null)}
          </Section>

          <Section
            title="Liens par séance"
            subtitle="● = séance courante de la classe choisie. Le numéro de séance est fixe : un lien de séance s'affiche aussi sur ses sessions bis et sur une session annulée."
            actions={
              nbClasses > 1 && (
                <AdminSelect
                  aria-label="Séance courante de"
                  value={String(classeChoisie.id)}
                  options={classes.map((c) => ({ value: String(c.id), label: libelleCreneau(c) }))}
                  onChange={(e) => setClasseId(e.target.value)}
                />
              )
            }
          >
            <div role="tablist" aria-label="Séances" style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', marginBottom: ADMIN_SPACING.lg }}>
              {SEANCES.map((n) => (
                <button
                  key={n}
                  role="tab"
                  type="button"
                  aria-selected={seance === n}
                  onClick={() => setSeance(n)}
                  style={{
                    padding: '6px 10px',
                    borderRadius: ADMIN_RADIUS.md,
                    border: `1px solid ${seance === n ? ADMIN_COLORS.primary : ADMIN_COLORS.border}`,
                    background: seance === n ? ADMIN_COLORS.primaryLight : ADMIN_COLORS.cardBg,
                    cursor: 'pointer',
                    fontWeight: seance === n ? 700 : 500,
                  }}
                >
                  {seanceCourante === n ? '● ' : ''}Séance {n} ({parSeance(n).length})
                </button>
              ))}
              {horsProgramme.length > 0 && (
                <button
                  role="tab"
                  type="button"
                  aria-selected={seance === 'hors'}
                  onClick={() => setSeance('hors')}
                  style={{ padding: '6px 10px', borderRadius: ADMIN_RADIUS.md, border: `1px dashed ${ADMIN_COLORS.border}`, background: 'transparent', cursor: 'pointer' }}
                >
                  Hors programme ({horsProgramme.length})
                </button>
              )}
            </div>
            {seance === 'hors' ? (
              <>
                <Banner tone="warning">
                  Ces liens sont rattachés à une séance qui n'existe plus (au-delà de la séance 14) : ils ne sont affichés à aucune classe. Choisissez une séance de 1 à 14 avec « Modifier », ou archivez-les.
                </Banner>
                {liste(horsProgramme, undefined)}
              </>
            ) : (
              <div role="tabpanel">{liste(parSeance(seance), seance)}</div>
            )}
          </Section>
        </>
      )}

      {formulaire && (
        <LienFormModal
          coursId={coursId}
          coursTitre={cours.titre}
          nbClasses={nbClasses}
          lien={formulaire.lien}
          seanceInitiale={formulaire.seanceInitiale ?? null}
          onClose={() => {
            setFormulaire(null);
            liens.reload();
          }}
          onDone={(message) => {
            setFormulaire(null);
            toast.success(message);
            liens.reload();
          }}
        />
      )}
      {archivage && (
        <AdminModal
          isOpen
          title="Archiver ce lien ?"
          size="sm"
          onClose={() => setArchivage(null)}
          footer={
            <>
              <AdminButton variant="secondary" onClick={() => setArchivage(null)}>
                Conserver le lien
              </AdminButton>
              <AdminButton variant="danger" onClick={archiver} loading={occupe}>
                Archiver le lien
              </AdminButton>
            </>
          }
        >
          <p style={{ marginTop: 0 }}>
            « {archivage.titre} » :
          </p>
          <p>
            <strong>Ce que cela change :</strong> le lien disparaît pour {nbClasses > 1 ? `les ${nbClasses} classes` : 'la classe'} du cours et pour les élèves. Il reste dans l'historique pendant
            6 mois : vous, ou un autre professeur du cours, pourrez le restaurer.
          </p>
        </AdminModal>
      )}
    </>
  );
}
