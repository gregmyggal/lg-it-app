import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import { Section } from '../components/ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import StatutBadge from '../components/ui/StatutBadge';
import AdminButton from '../components/AdminButton';
import EncoderSessionModal from '../components/timesheets/EncoderSessionModal';
import LiensSession from '../components/liens/LiensSession';
import LinkButton from '../components/ui/LinkButton';
import { useLiensCours } from '../hooks/useLiens';
import { useMesSessions } from '../hooks/useProfesseursClasses';
import { useToast } from '../hooks/useToast';
import { useClasse } from '../hooks/useClasses';
import { ETATS_ENCODAGE, SITUATIONS_SESSION, STATUTS_SESSION, estSessionBarree, phraseSituation } from '../utils/statuts';
import { formatDateCourte, formatHoraire, libelleClasse } from '../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../styles/AdminDesignSystem';

/** Sessions d'une de mes classes (mock-up 06) : ma situation (assigné / remplacé / remplaçant) et co-professeurs. */
export default function MesClasseSessionsPage() {
  const { id } = useParams();
  const classe = useClasse(id);
  const sessions = useMesSessions(id);
  const toast = useToast();
  const coursId = classe.data?.cours?.id;
  const liens = useLiensCours(coursId);
  const [aEncoder, setAEncoder] = useState(null);
  const titre = classe.data ? libelleClasse(classe.data) : 'Classe';

  return (
    <>
      <AdminPageHeader
        icon="📅"
        title={titre}
        breadcrumb={
          <>
            <Link to="/mes-classes">Mes classes</Link> › {titre}
          </>
        }
      />
      <AdminPageContent>
        {sessions.loading && <LoadingBlock message="Chargement des sessions…" lignes={4} />}
        {sessions.error && <ErrorBlock message={sessions.error} onRetry={sessions.reload} />}
        {sessions.data?.length === 0 && (
          <EmptyBlock icon="📅" title="Aucune session">
            Cette classe n'a pas de session qui vous concerne.
          </EmptyBlock>
        )}
        {sessions.data && (
          <p style={{ fontSize: '13px' }}>
            Toutes vos heures du mois en un seul passage : <Link to="/timesheets">Encoder mon mois</Link>
          </p>
        )}
        {coursId && (
          <p style={{ fontSize: '13px' }}>
            <LinkButton to={`/mes-ressources/${coursId}`} size="sm">
              Gérer les liens du cours
            </LinkButton>
          </p>
        )}
        {sessions.data?.length > 0 && (
          <Section title="Sessions" subtitle={`${sessions.data.length} session${sessions.data.length > 1 ? 's' : ''}`}>
            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.md }}>
              {sessions.data.map((s) => {
                const barree = estSessionBarree(s.statut);
                return (
                  <li
                    key={s.id}
                    style={{
                      background: ADMIN_COLORS.cardBg,
                      border: `1px solid ${ADMIN_COLORS.border}`,
                      borderRadius: ADMIN_RADIUS.md,
                      padding: ADMIN_SPACING.lg,
                      opacity: barree ? 0.75 : 1,
                    }}
                  >
                    <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center' }}>
                      <strong>{s.libelle}</strong>
                      <StatutBadge table={STATUTS_SESSION} valeur={s.statut} />
                      {s.ma_situation && <StatutBadge table={SITUATIONS_SESSION} valeur={s.ma_situation.type} />}
                      {!barree && s.ma_situation?.type !== 'remplace_par' && s.encodage && (
                        <StatutBadge table={ETATS_ENCODAGE} valeur={s.encodage} />
                      )}
                    </div>
                    <div style={{ textDecoration: barree ? 'line-through' : undefined }}>
                      {formatDateCourte(s.date)} · {formatHoraire(s.heure_debut, s.heure_fin)}
                    </div>
                    {barree && s.motif_annulation && <div style={{ fontSize: '13px' }}>Motif : {s.motif_annulation}</div>}
                    {s.ma_situation && s.ma_situation.type !== 'assignee' && (
                      <div style={{ fontSize: '13px', marginTop: ADMIN_SPACING.xs }}>{phraseSituation(s.ma_situation)}</div>
                    )}
                    {s.peut_encoder && s.encodage === 'a_encoder' && (
                      <div style={{ marginTop: ADMIN_SPACING.sm }}>
                        <AdminButton size="sm" onClick={() => setAEncoder(s)}>
                          Encoder mes heures
                        </AdminButton>
                      </div>
                    )}
                    {liens.data && <LiensSession liens={liens.data.data} seanceNumero={s.seance_numero} />}
                    {s.co_professeurs.length > 0 && (
                      <div style={{ fontSize: '13px', color: ADMIN_COLORS.textSecondary, marginTop: ADMIN_SPACING.xs }}>
                        Avec : {s.co_professeurs.map((p) => p.nom).join(', ')}
                      </div>
                    )}
                  </li>
                );
              })}
            </ul>
          </Section>
        )}
      </AdminPageContent>
      {aEncoder && (
        <EncoderSessionModal
          session={aEncoder}
          classeLibelle={titre}
          onClose={() => setAEncoder(null)}
          onDone={(message) => {
            setAEncoder(null);
            toast.success(message);
            sessions.reload();
          }}
        />
      )}
    </>
  );
}
