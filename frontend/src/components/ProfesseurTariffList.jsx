import { useState, useEffect } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

export default function ProfesseurTariffList({
  professeurId,
  onEdit,
  onTerminate,
  onDelete,
  readonly = false,
}) {
  const [tariffs, setTariffs] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadTariffs();
  }, [professeurId]);

  async function loadTariffs() {
    try {
      setLoading(true);
      const response = await client.get(`/professeurs/${professeurId}/tarifs`);
      setTariffs(response.data);
      setError(null);
    } catch (err) {
      setError('Impossible de charger les tarifs');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) {
    return <div style={{ padding: '16px', color: '#999' }}>Chargement des tarifs…</div>;
  }

  if (error) {
    return <div style={{ padding: '16px', color: ADMIN_COLORS.error }}>⚠️ {error}</div>;
  }

  if (!tariffs || tariffs.length === 0) {
    return (
      <div style={{
        padding: '32px',
        textAlign: 'center',
        color: '#9ca3af',
        background: '#f9fafb',
        borderRadius: '8px',
        border: `1px solid ${ADMIN_COLORS.border}`,
      }}>
        <p style={{ margin: 0 }}>Aucun tarif défini</p>
        {!readonly && (
          <p style={{ margin: '8px 0 0 0', fontSize: '0.9em' }}>Créez un tarif pour commencer</p>
        )}
      </div>
    );
  }

  const isEffective = (tariff) => {
    const today = new Date().toISOString().split('T')[0];
    return tariff.date_debut <= today && (!tariff.date_fin || tariff.date_fin > today);
  };

  return (
    <div style={{
      background: 'white',
      borderRadius: '8px',
      border: `1px solid ${ADMIN_COLORS.border}`,
      overflow: 'hidden',
      boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
    }}>
      <table style={{
        width: '100%',
        borderCollapse: 'collapse',
      }}>
        <thead>
          <tr style={{
            background: '#f9fafb',
            borderBottom: `1px solid ${ADMIN_COLORS.border}`,
          }}>
            <th style={{
              padding: '16px',
              textAlign: 'left',
              fontSize: '12px',
              fontWeight: 600,
              color: ADMIN_COLORS.textPrimary,
              textTransform: 'uppercase',
              letterSpacing: '0.5px',
            }}>
              Tarif Horaire
            </th>
            <th style={{
              padding: '16px',
              textAlign: 'left',
              fontSize: '12px',
              fontWeight: 600,
              color: ADMIN_COLORS.textPrimary,
              textTransform: 'uppercase',
              letterSpacing: '0.5px',
            }}>
              Période
            </th>
            <th style={{
              padding: '16px',
              textAlign: 'left',
              fontSize: '12px',
              fontWeight: 600,
              color: ADMIN_COLORS.textPrimary,
              textTransform: 'uppercase',
              letterSpacing: '0.5px',
            }}>
              Statut
            </th>
            {!readonly && (
              <th style={{
                padding: '16px',
                textAlign: 'right',
                fontSize: '12px',
                fontWeight: 600,
                color: ADMIN_COLORS.textPrimary,
                textTransform: 'uppercase',
                letterSpacing: '0.5px',
              }}>
                Actions
              </th>
            )}
          </tr>
        </thead>
        <tbody>
          {tariffs.map((tariff, idx) => {
            const effective = isEffective(tariff);
            const dateDebut = new Date(tariff.date_debut).toLocaleDateString('fr-FR');
            const dateFin = tariff.date_fin
              ? new Date(tariff.date_fin).toLocaleDateString('fr-FR')
              : '∞ (toujours)';

            return (
              <tr
                key={tariff.id}
                style={{
                  borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                  background: idx % 2 === 0 ? 'white' : '#f9fafb',
                  opacity: effective ? 1 : 0.7,
                }}
              >
                <td style={{
                  padding: '16px',
                  fontSize: '16px',
                  fontWeight: '600',
                  color: effective ? '#059669' : ADMIN_COLORS.textPrimary,
                }}>
                  {tariff.tarif_horaire_eur.toFixed(2)}€/h
                  {effective && (
                    <span style={{
                      marginLeft: '8px',
                      fontSize: '12px',
                      background: '#d1fae5',
                      color: '#065f46',
                      padding: '2px 8px',
                      borderRadius: '4px',
                    }}>
                      ✓ Actif
                    </span>
                  )}
                </td>
                <td style={{
                  padding: '16px',
                  fontSize: '14px',
                  color: ADMIN_COLORS.textPrimary,
                }}>
                  <div>{dateDebut}</div>
                  <div style={{ fontSize: '12px', color: '#6b7280', marginTop: '4px' }}>
                    jusqu'au {dateFin}
                  </div>
                </td>
                <td style={{
                  padding: '16px',
                  fontSize: '14px',
                  color: effective ? '#059669' : '#9ca3af',
                }}>
                  {effective ? 'Actif' : 'Historique'}
                </td>
                {!readonly && (
                  <td style={{
                    padding: '16px',
                    textAlign: 'right',
                  }}>
                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                      <AdminButton
                        variant="secondary"
                        size="sm"
                        icon="✏️"
                        onClick={() => onEdit(tariff)}
                        title="Modifier ce tarif"
                      >
                        Éditer
                      </AdminButton>

                      {!tariff.date_fin && (
                        <AdminButton
                          variant="secondary"
                          size="sm"
                          icon="⏹️"
                          onClick={() => onTerminate(tariff)}
                          title="Terminer ce tarif (définir date_fin)"
                        >
                          Arrêter
                        </AdminButton>
                      )}

                      <AdminButton
                        variant="danger"
                        size="sm"
                        icon="🗑️"
                        onClick={() => onDelete(tariff)}
                        title="Supprimer ce tarif"
                      >
                        Supprimer
                      </AdminButton>
                    </div>
                  </td>
                )}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
