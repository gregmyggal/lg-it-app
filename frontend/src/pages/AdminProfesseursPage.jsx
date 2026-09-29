import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../api/client';
import AdminButton from '../components/AdminButton';
import {
  AdminPageHeader,
  AdminPageContent,
} from '../components/AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';
import ProfesseurTariffForm from '../components/ProfesseurTariffForm';

export default function AdminProfesseursPage() {
  const navigate = useNavigate();
  const [professeurs, setProfesseurs] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [selectedProf, setSelectedProf] = useState(null);
  const [showTarifForm, setShowTarifForm] = useState(false);
  const [tariffs, setTariffs] = useState({});
  const [editingTariff, setEditingTariff] = useState(null);

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    try {
      const profsRes = await client.get('/professeurs');
      setProfesseurs(profsRes.data);

      // Charger les tarifs pour chaque professeur
      const tariffMap = {};
      for (const prof of profsRes.data) {
        try {
          const tarRes = await client.get(`/professeurs/${prof.id}/tarifs`);
          tariffMap[prof.id] = tarRes.data;
        } catch {
          tariffMap[prof.id] = [];
        }
      }
      setTariffs(tariffMap);
      setError(null);
    } catch (err) {
      setError('Impossible de charger les données');
      console.error(err);
    }
  }

  async function handleDeleteTariff(profId, tarifId) {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer ce tarif ?')) {
      return;
    }

    try {
      await client.delete(`/professeurs/${profId}/tarifs/${tarifId}`);
      setTariffs(prev => ({
        ...prev,
        [profId]: prev[profId].filter(t => t.id !== tarifId),
      }));
      setSuccess('Tarif supprimé avec succès');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la suppression');
    }
  }

  if (!professeurs) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  // Calculer les stats par professeur
  const getProfStats = (profId) => {
    const profTariffs = tariffs[profId] || [];
    const activeTariff = profTariffs.find(t => {
      const today = new Date().toISOString().split('T')[0];
      return t.date_debut <= today && (!t.date_fin || t.date_fin > today);
    });
    return {
      tariffCount: profTariffs.length,
      activeTariff: activeTariff?.tarif_horaire_eur,
    };
  };

  return (
    <>
      <AdminPageHeader
        icon="👨‍🏫"
        title="Gestion des Professeurs & Tarifs"
        description="Gérez vos professeurs et leurs tarifs horaires"
        badge={`${professeurs.length} professeurs`}
      />

      <AdminPageContent>
        {error && (
          <div style={{
            background: '#fee2e2',
            color: ADMIN_COLORS.error,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
          }}>
            ⚠️ {error}
          </div>
        )}

        {success && (
          <div style={{
            background: '#d1fae5',
            color: ADMIN_COLORS.success,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
          }}>
            ✓ {success}
          </div>
        )}

        {/* Liste des professeurs */}
        <div style={{ display: 'grid', gap: '24px' }}>
          {professeurs.map((prof) => {
            const stats = getProfStats(prof.id);
            const profTariffs = tariffs[prof.id] || [];

            return (
              <div
                key={prof.id}
                style={{
                  background: 'white',
                  borderRadius: '8px',
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  overflow: 'hidden',
                }}
              >
                {/* En-tête */}
                <div style={{
                  background: '#f9fafb',
                  padding: '20px',
                  borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  gap: '20px',
                }}>
                  <div style={{ flex: 1, cursor: 'pointer' }} onClick={() => navigate(`/admin/professeurs/${prof.id}`)}>
                    <h3 style={{ margin: '0 0 8px 0' }}>
                      {prof.prenom} {prof.nom}
                    </h3>
                    <p style={{ margin: 0, color: '#6b7280', fontSize: '0.9em' }}>
                      {prof.email}
                    </p>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '12px' }}>
                    <AdminButton
                      variant="primary"
                      size="sm"
                      icon="👁️"
                      onClick={() => navigate(`/admin/professeurs/${prof.id}`)}
                    >
                      Voir détails
                    </AdminButton>
                    <div style={{ textAlign: 'right' }}>
                      <div style={{
                        fontSize: '12px',
                        fontWeight: '600',
                        color: '#6b7280',
                        marginBottom: '4px',
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px',
                      }}>
                        Tarif Actif
                      </div>
                      <div style={{
                        fontSize: '20px',
                        fontWeight: 'bold',
                        color: stats.activeTariff ? '#059669' : '#9ca3af',
                      }}>
                        {stats.activeTariff
                          ? `${parseFloat(stats.activeTariff).toFixed(2)}€/h`
                          : '—'
                        }
                      </div>
                    </div>
                  </div>
                </div>

                {/* Tarifs */}
                <div style={{ padding: '20px', background: '#fafafa' }}>
                  <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: '16px',
                  }}>
                    <h4 style={{ margin: 0, fontWeight: '600' }}>
                      📊 Tarifs ({profTariffs.length})
                    </h4>
                    <AdminButton
                      variant="primary"
                      size="sm"
                      icon="➕"
                      onClick={() => {
                        setSelectedProf(prof.id);
                        setShowTarifForm(true);
                        setEditingTariff(null);
                      }}
                    >
                      Ajouter
                    </AdminButton>
                  </div>

                  {profTariffs.length === 0 ? (
                    <div style={{
                      textAlign: 'center',
                      color: '#9ca3af',
                      padding: '20px',
                      background: 'white',
                      borderRadius: '6px',
                      border: `1px solid ${ADMIN_COLORS.border}`,
                    }}>
                      📭 Aucun tarif
                    </div>
                  ) : (
                    <div style={{ display: 'grid', gap: '12px' }}>
                      {profTariffs.map((tariff) => {
                        const isActive = () => {
                          const today = new Date().toISOString().split('T')[0];
                          return tariff.date_debut <= today &&
                            (!tariff.date_fin || tariff.date_fin > today);
                        };
                        const active = isActive();

                        return (
                          <div
                            key={tariff.id}
                            style={{
                              background: 'white',
                              border: `1px solid ${ADMIN_COLORS.border}`,
                              borderRadius: '6px',
                              padding: '16px',
                              display: 'flex',
                              justifyContent: 'space-between',
                              alignItems: 'center',
                              opacity: active ? 1 : 0.7,
                            }}
                          >
                            <div>
                              <div style={{
                                fontSize: '18px',
                                fontWeight: 'bold',
                                color: active ? '#059669' : ADMIN_COLORS.textPrimary,
                                marginBottom: '4px',
                              }}>
                                {parseFloat(tariff.tarif_horaire_eur).toFixed(2)}€/h
                              </div>
                              <div style={{ fontSize: '13px', color: '#6b7280' }}>
                                {new Date(tariff.date_debut).toLocaleDateString('fr-FR')}
                                {tariff.date_fin && ` → ${new Date(tariff.date_fin).toLocaleDateString('fr-FR')}`}
                                {!tariff.date_fin && ' → ∞'}
                              </div>
                            </div>
                            <div style={{ display: 'flex', gap: '8px' }}>
                              <AdminButton
                                variant="secondary"
                                size="sm"
                                icon="✏️"
                                onClick={() => {
                                  setSelectedProf(prof.id);
                                  setEditingTariff(tariff);
                                  setShowTarifForm(true);
                                }}
                              >
                              </AdminButton>
                              <AdminButton
                                variant="danger"
                                size="sm"
                                icon="🗑️"
                                onClick={() => handleDeleteTariff(prof.id, tariff.id)}
                              >
                              </AdminButton>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Modal Tarif */}
        {showTarifForm && selectedProf && (
          <div style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'rgba(0,0,0,0.5)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1000,
          }} onClick={() => setShowTarifForm(false)}>
            <div
              style={{
                background: 'white',
                borderRadius: '8px',
                padding: '24px',
                maxWidth: '500px',
                width: '90%',
              }}
              onClick={(e) => e.stopPropagation()}
            >
              <ProfesseurTariffForm
                professeurId={selectedProf}
                tariff={editingTariff}
                onSuccess={() => {
                  setShowTarifForm(false);
                  setEditingTariff(null);
                  loadData();
                  setSuccess(editingTariff ? 'Tarif modifié' : 'Tarif créé');
                  setTimeout(() => setSuccess(null), 2000);
                }}
                onCancel={() => {
                  setShowTarifForm(false);
                  setEditingTariff(null);
                }}
              />
            </div>
          </div>
        )}
      </AdminPageContent>
    </>
  );
}
