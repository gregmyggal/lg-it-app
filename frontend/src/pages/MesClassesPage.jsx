import { useState } from 'react';
import { Link } from 'react-router-dom';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import { Section } from '../components/ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import LinkButton from '../components/ui/LinkButton';
import StatutBadge from '../components/ui/StatutBadge';
import { AdminCheckbox } from '../components/AdminFormField';
import { useMesClasses } from '../hooks/useProfesseursClasses';
import { ROLES_PROFESSEUR, STATUTS_ASSIGNATION, statutAssignation } from '../utils/statuts';
import { formatDateCourte, formatHoraire, libelleClasse } from '../utils/dates';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../styles/AdminDesignSystem';

const carte = {
  background: ADMIN_COLORS.cardBg,
  border: `1px solid ${ADMIN_COLORS.border}`,
  borderRadius: ADMIN_RADIUS.md,
  padding: ADMIN_SPACING.lg,
};

/** Portail professeur « Mes classes » (mock-up 06, mobile-first) : classes, co-professeurs, remplacements à venir. */
export default function MesClassesPage() {
  const [inclureTerminees, setInclureTerminees] = useState(false);
  const mes = useMesClasses(inclureTerminees);
  const classes = mes.data?.classes || [];
  const remplacements = mes.data?.remplacements || [];

  return (
    <>
      <AdminPageHeader
        icon="🏫"
        title="Mes classes"
        description="Vos classes, leurs prochaines sessions et vos remplacements"
      />
      <AdminPageContent>
        {mes.loading && <LoadingBlock message="Chargement de vos classes…" lignes={3} />}
        {mes.error && <ErrorBlock message={mes.error} onRetry={mes.reload} />}

        {mes.data && (
          <>
            {remplacements.length > 0 && (
              <Section title="Mes remplacements à venir" subtitle="Sessions où vous remplacez un professeur">
                <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.md }}>
                  {remplacements.map((s) => (
                    <li key={s.id} style={carte}>
                      <strong>{s.classe?.cours?.titre}</strong> — {s.libelle}
                      <div>
                        {formatDateCourte(s.date)} · {formatHoraire(s.heure_debut, s.heure_fin)}
                      </div>
                      <StatutBadge table={ROLES_PROFESSEUR} valeur="remplacant" />
                    </li>
                  ))}
                </ul>
              </Section>
            )}

            <Section
              title="Mes classes"
              actions={
                <AdminCheckbox
                  id="mes-terminees"
                  label="Voir aussi les classes terminées"
                  checked={inclureTerminees}
                  onChange={(e) => setInclureTerminees(e.target.checked)}
                />
              }
            >
              {classes.length === 0 ? (
                <EmptyBlock icon="🏫" title="Aucune classe pour le moment">
                  Dès que la direction vous assigne à une classe, elle apparaît ici avec toutes ses sessions.
                </EmptyBlock>
              ) : (
                <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.lg }}>
                  {classes.map((a) => {
                    const prochaine = a.classe.prochaine_session;
                    return (
                      <li key={a.assignation_id} style={carte}>
                        <div style={{ display: 'flex', gap: ADMIN_SPACING.sm, flexWrap: 'wrap', alignItems: 'center' }}>
                          <strong style={{ fontSize: '16px' }}>{libelleClasse(a.classe)}</strong>
                          <StatutBadge table={ROLES_PROFESSEUR} valeur={a.role} />
                          {!a.actif && <StatutBadge table={STATUTS_ASSIGNATION} valeur={statutAssignation(false)} />}
                        </div>
                        <div style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px', margin: `${ADMIN_SPACING.xs} 0` }}>
                          {a.classe.annee_scolaire?.libelle}, période {a.classe.periode?.numero}
                        </div>
                        <div style={{ margin: `${ADMIN_SPACING.sm} 0` }}>
                          {prochaine ? (
                            <>
                              Prochaine session : <strong>{prochaine.libelle}</strong>, {formatDateCourte(prochaine.date)}
                            </>
                          ) : (
                            'Aucune session à venir'
                          )}
                        </div>
                        {a.co_professeurs.length > 0 && (
                          <div style={{ fontSize: '13px', marginBottom: ADMIN_SPACING.md }}>
                            Avec : {a.co_professeurs.map((p) => p.nom).join(', ')}
                          </div>
                        )}
                        <LinkButton to={`/mes-classes/${a.classe.id}`} size="sm">
                          Voir les sessions
                        </LinkButton>
                      </li>
                    );
                  })}
                </ul>
              )}
            </Section>

            <p style={{ fontSize: '13px' }}>
              Besoin des liens d'un cours ? <Link to="/mes-ressources">Liens de mes cours</Link>
            </p>
          </>
        )}
      </AdminPageContent>
    </>
  );
}
