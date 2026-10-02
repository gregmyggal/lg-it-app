import { useEffect, useState } from 'react';
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
import { getErrorMessage } from '../api/errors';

export default function AdminProfesseurDetail() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [professeur, setProfesseur] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [editMode, setEditMode] = useState(false);

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

  async function handleDeleteProfesseur() {
    if (!window.confirm('Supprimer définitivement ce professeur ? Possible uniquement s\'il n\'a aucune donnée liée (sinon, désactivez-le).')) {
      return;
    }

    try {
      await client.delete(`/professeurs/${id}`);
      navigate('/admin/professeurs');
    } catch (err) {
      setError(getErrorMessage(err, 'Erreur lors de la suppression'));
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

  const statusColor = professeur.statut === 'actif' ? 'green' : 'red';

  return (
    <>
      <AdminPageHeader
        icon="👨‍🏫"
        title={`${professeur.prenom} ${professeur.nom}`}
        description={professeur.email}
        badge={professeur.statut === 'actif' ? '✅ Actif' : '⏸️ Inactif'}
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
                    label={professeur.statut === 'actif' ? 'Actif' : 'Inactif'}
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
          <CompteProfesseurSection
            professeur={professeur}
            onChange={(message) => {
              setSuccess(message);
              loadProfesseur();
              setTimeout(() => setSuccess(null), 4000);
            }}
          />
        </div>

        <div style={{ marginBottom: ADMIN_SPACING.xl }}>
          <CompteBancaireSection professeur={professeur} onSaved={loadProfesseur} />
        </div>

        {/* Classes (CLS-01 T2 : remplace l'assignation de cours) */}
        <div style={{ marginBottom: ADMIN_SPACING.xl }}>
          <ClassesProfesseurSection professeur={professeur} />
        </div>

        {/* Tarifs */}
        <AdminCard style={{ marginBottom: ADMIN_SPACING.xl }}>
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

          <AdminButton
            variant="danger"
            icon="🗑️"
            onClick={handleDeleteProfesseur}
          >
            Supprimer
          </AdminButton>
        </div>
      </AdminPageContent>

    </>
  );
}
