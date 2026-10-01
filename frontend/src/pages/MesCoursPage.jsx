import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';
import { Section } from '../components/ui/Card';
import { EmptyBlock, ErrorBlock, LoadingBlock } from '../components/ui/DataStates';
import LinkButton from '../components/ui/LinkButton';
import { useCours } from '../hooks/useCours';
import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING } from '../styles/AdminDesignSystem';

/**
 * « Liens de mes cours » (route `/mes-ressources`, CLS-01 T4) : les cours où le professeur a une classe active, avec le
 * nombre de liens généraux et de séance, et l'accès à l'écran « Liens du cours » (consultation, ajout, historique).
 * Remplace l'ancienne page « Ressources de mes cours ».
 */
export default function MesCoursPage() {
  const cours = useCours();

  return (
    <>
      <AdminPageHeader icon="🔗" title="Liens de mes cours" description="Les liens des cours où vous avez une classe active : vous les consultez et les adaptez pour toutes les classes du cours." />
      <AdminPageContent>
        {cours.loading && <LoadingBlock message="Chargement de vos cours…" lignes={3} />}
        {cours.error && <ErrorBlock message={cours.error} onRetry={cours.reload} />}
        {cours.data && (
          <Section title="Mes cours">
            {cours.data.length === 0 ? (
              <EmptyBlock icon="📚" title="Aucun cours pour le moment">
                Dès que la direction vous assigne à une classe, les liens de son cours apparaissent ici.
              </EmptyBlock>
            ) : (
              <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: ADMIN_SPACING.lg }}>
                {cours.data.map((c) => (
                  <li key={c.id} style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: ADMIN_RADIUS.md, padding: ADMIN_SPACING.lg, background: ADMIN_COLORS.cardBg }}>
                    <strong style={{ fontSize: '16px' }}>{c.titre}</strong>
                    <div style={{ color: ADMIN_COLORS.textSecondary, fontSize: '13px', margin: `${ADMIN_SPACING.xs} 0 ${ADMIN_SPACING.md}` }}>
                      {c.liens_generaux_count} généra{c.liens_generaux_count > 1 ? 'ux' : 'l'} · {c.liens_seance_count} de séance
                    </div>
                    <LinkButton to={`/mes-ressources/${c.id}`}>Liens du cours</LinkButton>
                  </li>
                ))}
              </ul>
            )}
          </Section>
        )}
      </AdminPageContent>
    </>
  );
}
