import { useEffect, useState } from 'react';
import EmployeurMoisSection from './employeurs/EmployeurMoisSection';
import { useParams, useNavigate } from 'react-router-dom';
import client from '../api/client';
import AdminButton, { AdminIconButton } from './AdminButton';
import {
  AdminPageHeader,
  AdminPageContent,
  AdminBadge,
  AdminCard,
  AdminCardHeader,
  AdminCardBody,
  AdminCardFooter,
} from './AdminPageLayout';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';
import ClassesProfesseurSection from './professeurs/ClassesProfesseurSection';
import CompteProfesseurSection from './professeurs/CompteProfesseurSection';
import CompteBancaireSection from './professeurs/CompteBancaireSection';
import { ArchiverProfesseurModal, SupprimerProfesseurModal } from './professeurs/CycleVieProfesseurModals';
import { useToast } from '../hooks/useToast';

export default function AdminProfesseurDetail() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [professeur, setProfesseur] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [editMode, setEditMode] = useState(false);
  const [modal, setModal] = useState(null); // 'archiver' | 'supprimer'
  const toast = useToast();

  useEffect(() => {
    loadProfesseur();
  }, [id]);

  async function loadProfesseur() {
    try {
      const res = await client.get(`/professeurs/${id}`);
      setProfesseur(res.data);
      setError(null);
    } catch (err) {
      setError('Impossible de charger le professeur');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: 'var(--c-text-2)' }}>
        Chargement…
      </div>
    );
  }

  if (!professeur) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: 'var(--c-error)' }}>
        ❌ Professeur non trouvé
      </div>
    );
  }

  const actif = professeur.statut === 'actif';
  const statusColor = actif ? 'green' : 'purple';

  function apresChangement(message) {
    setSuccess(message);
    loadProfesseur();
    setTimeout(() => setSuccess(null), 4000);
  }

  return (
    <>
      <AdminPageHeader
        icon="👨‍🏫"
        title={`${professeur.prenom} ${professeur.nom}`}
        description={professeur.email}
        badge={actif ? '✅ Actif' : '🗄️ Archivé'}
      />

      <AdminPageContent>
        {error && (
          <div
            style={{
              background: 'var(--tone-error-bg)',
              color: ADMIN_COLORS.error,
              padding: '16px',
              borderRadius: '8px',
              marginBottom: '16px',
            }}
          >
            ⚠️ {error}
          </div>
        )}

        {success && (
          <div
            style={{
              background: 'var(--tone-success-bg)',
              color: ADMIN_COLORS.success,
              padding: '16px',
              borderRadius: '8px',
              marginBottom: '16px',
            }}
          >
            ✓ {success}
          </div>
        )}

        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 360px), 1fr))',
            gap: ADMIN_SPACING.xl,
            marginBottom: ADMIN_SPACING.xl,
          }}
        >
          {/* Infos Personnelles & Contrat */}
          <AdminCard>
            <AdminCardHeader title="📋 Informations Personnelles" />
            <AdminCardBody>
              <div style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
                <div>
                  <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                    EMAIL
                  </div>
                  <div>{professeur.email}</div>
                </div>

                {professeur.telephone && (
                  <div>
                    <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                      TÉLÉPHONE
                    </div>
                    <div>{professeur.telephone}</div>
                  </div>
                )}
              </div>
            </AdminCardBody>
          </AdminCard>

          {/* Contrat & Emploi */}
          <AdminCard>
            <AdminCardHeader title="💼 Contrat & Emploi" />
            <AdminCardBody>
              <div style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
                <div>
                  <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                    TYPE DE CONTRAT
                  </div>
                  <div>
                    {professeur.type_contrat
                      ? professeur.type_contrat.charAt(0).toUpperCase() +
                        professeur.type_contrat.slice(1)
                      : '—'}
                  </div>
                </div>

                <div>
                  <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                    STATUT
                  </div>
                  <AdminBadge
                    label={actif ? 'Actif' : 'Archivé'}
                    color={statusColor}
                  />
                </div>

                <div>
                  <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                    PÉRIODE D'EMPLOI
                  </div>
                  <div style={{ fontSize: '14px' }}>
                    {new Date(professeur.date_entree).toLocaleDateString('fr-FR')}
                    {professeur.date_sortie &&
                      ` → ${new Date(professeur.date_sortie).toLocaleDateString('fr-FR')}`}
                    {!professeur.date_sortie && ' → ∞'}
                  </div>
                </div>
              </div>
            </AdminCardBody>
          </AdminCard>
        </div>

        <div style={{ marginBottom: ADMIN_SPACING.xl }}>
          <CompteProfesseurSection professeur={professeur} onChange={apresChangement} />
        </div>

        <div style={{ marginBottom: ADMIN_SPACING.xl }}>
          <CompteBancaireSection professeur={professeur} onSaved={loadProfesseur} />
        </div>

        {/* Classes (CLS-01 T2 : remplace l'assignation de cours) */}
        <div style={{ marginBottom: ADMIN_SPACING.xl }}>
          <ClassesProfesseurSection professeur={professeur} />
        </div>

        {/* Tarifs */}
        <div style={{ marginBottom: ADMIN_SPACING.xl }}>
        <AdminCard>
          <AdminCardHeader
            title={`💰 Tarif Horaire Actuel`}
            actions={
              <AdminButton
                variant="secondary"
                size="sm"
                icon="✏️"
                onClick={() => navigate(`/admin/professeurs/${id}/tarifs`)}
              >
                Gérer
              </AdminButton>
            }
          />

          <AdminCardBody>
            {professeur.tarifs && professeur.tarifs.length > 0 ? (
              <div style={{ display: 'grid', gap: ADMIN_SPACING.lg }}>
                <div
                  style={{
                    fontSize: '28px',
                    fontWeight: 'bold',
                    color: ADMIN_COLORS.success,
                  }}
                >
                  {parseFloat(
                    professeur.tarifs.find(
                      (t) =>
                        new Date(t.date_debut) <= new Date() &&
                        (!t.date_fin || new Date(t.date_fin) > new Date()),
                    )?.tarif_horaire_eur || 0,
                  ).toFixed(2)}
                  €/h
                </div>

                <div style={{ fontSize: '12px', color: 'var(--c-text-2)' }}>
                  {professeur.tarifs.length} tarif{professeur.tarifs.length > 1 ? 's' : ''} dans l'historique
                </div>
              </div>
            ) : (
              <div style={{ color: 'var(--c-text-3)' }}>📭 Aucun tarif défini</div>
            )}
          </AdminCardBody>
        </AdminCard>
        </div>

        {/* Activité */}
        <AdminCard>
          <AdminCardHeader title="📊 Activité" />

          <AdminCardBody>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 360px), 1fr))', gap: ADMIN_SPACING.lg }}>
              <div>
                <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                  HEURES ENCODÉES (CE MOIS)
                </div>
                <div style={{ fontSize: '20px', fontWeight: 'bold' }}>
                  {professeur.timesheets_count || 0}h
                </div>
              </div>

              <div>
                <div style={{ fontSize: '12px', color: 'var(--c-text-2)', fontWeight: 600, marginBottom: '4px' }}>
                  TIMESHEETS
                </div>
                <div style={{ fontSize: '14px' }}>
                  {professeur.timesheets_submitted || 0} soumis
                </div>
              </div>
            </div>
          </AdminCardBody>

          <AdminCardFooter>
            <AdminButton
              variant="secondary"
              size="sm"
              onClick={() => navigate(`/admin/timesheets?professeur=${id}`)}
            >
              Voir timesheets
            </AdminButton>
          </AdminCardFooter>
        </AdminCard>

        {/* EMP-01 : employeur par mois */}
        <div style={{ marginTop: ADMIN_SPACING.lg }}>
          <EmployeurMoisSection professeurId={professeur.id} professeurNom={`${professeur.prenom} ${professeur.nom}`} />
        </div>

        {/* Actions */}
        <div
          style={{
            display: 'flex',
            gap: ADMIN_SPACING.lg,
            marginTop: ADMIN_SPACING.xl,
            justifyContent: 'flex-end',
          }}
        >
          <AdminButton
            variant="secondary"
            onClick={() => navigate('/admin/professeurs')}
          >
            ← Retour
          </AdminButton>

          <AdminButton
            variant="secondary"
            icon="✏️"
            onClick={() => setEditMode(true)}
          >
            Éditer
          </AdminButton>

        </div>

        {/* PROF-02 : suppression définitive, à l'écart des actions courantes (l'archivage est recommandé). */}
        <section
          aria-labelledby="zone-sensible-titre"
          style={{ marginTop: ADMIN_SPACING.xl, border: '1px solid var(--tone-error-bd)', borderRadius: '12px', padding: '20px', background: 'var(--c-card)' }}
        >
          <h2 id="zone-sensible-titre" style={{ margin: '0 0 8px', fontSize: '16px', color: 'var(--tone-error-fg)' }}>Zone sensible</h2>
          <p style={{ marginTop: 0 }}>
            Supprimer définitivement ce professeur et toutes ses données.{actif && ' Pour un départ, préférez l\u2019archivage.'}
          </p>
          <AdminButton variant="secondary" size="sm" onClick={() => setModal('supprimer')} style={{ color: 'var(--tone-error-fg)' }}>
            Supprimer le professeur…
          </AdminButton>
        </section>
      </AdminPageContent>

      {modal === 'supprimer' && (
        <SupprimerProfesseurModal
          professeur={professeur}
          onArchiver={actif ? () => setModal('archiver') : null}
          onClose={() => setModal(null)}
          onSupprime={(message) => {
            toast.success(message);
            navigate('/admin/professeurs');
          }}
        />
      )}
      {modal === 'archiver' && (
        <ArchiverProfesseurModal
          professeur={professeur}
          onClose={() => setModal(null)}
          onDone={(message) => { setModal(null); apresChangement(message); }}
        />
      )}
    </>
  );
}
