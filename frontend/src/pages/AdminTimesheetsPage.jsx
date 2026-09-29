import { useEffect, useState } from 'react';
import client from '../api/client';
import { useAuth } from '../auth/AuthContext';
import AdminButton from '../components/AdminButton';
import {
  AdminPageHeader,
  AdminPageContent,
  AdminBadge,
} from '../components/AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

const STATUT_LABELS = {
  brouillon: 'Brouillon',
  soumis: 'Soumis',
  confirmé: 'Confirmé',
  généré: 'Généré',
};

const STATUT_COLORS = {
  brouillon: 'amber',
  soumis: 'blue',
  confirmé: 'indigo',
  généré: 'green',
};

export default function AdminTimesheetsPage() {
  const { user } = useAuth();
  const [timesheets, setTimesheets] = useState(null);
  const [error, setError] = useState(null);
  const [selectedMonth, setSelectedMonth] = useState(new Date().toISOString().slice(0, 7));
  const [selectedProfesseur, setSelectedProfesseur] = useState(null);
  const [viewMode, setViewMode] = useState('monthly'); // 'monthly' or 'professor'
  const [professeurs, setProfesseurs] = useState([]);
  const [pdfGenerationHistory, setPdfGenerationHistory] = useState([]);

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    try {
      const [tsRes, profRes] = await Promise.all([
        client.get('/timesheets'),
        client.get('/professeurs'),
      ]);
      setTimesheets(tsRes.data);
      setProfesseurs(profRes.data);
      // TODO: Ajouter historique générations PDF quand implémenté au backend
      setPdfGenerationHistory([]);
      setError(null);
    } catch (err) {
      setError('Impossible de charger les données');
      console.error(err);
    }
  }

  if (!timesheets) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  // Filtrer par mois et professeur
  const [year, month] = selectedMonth.split('-').map(Number);
  const filteredTS = timesheets.filter((ts) => {
    const tsDate = new Date(ts.date_prestation);
    const matchMonth = tsDate.getFullYear() === year && tsDate.getMonth() === month - 1;
    const matchProf = !selectedProfesseur || ts.professeur_id === selectedProfesseur;
    return matchMonth && matchProf;
  });

  // Calculer les statistiques
  const stats = {
    totalHeures: filteredTS.reduce((sum, ts) => sum + parseFloat(ts.nombre_heures || 0), 0),
    totalMontant: filteredTS.reduce((sum, ts) => {
      const prof = professeurs.find(p => p.id === ts.professeur_id);
      const tarif = prof?.tarif_effectif?.tarif_horaire_eur || 0;
      return sum + (parseFloat(ts.nombre_heures || 0) * parseFloat(tarif));
    }, 0),
    byStatus: {
      brouillon: filteredTS.filter(ts => ts.statut_validation === 'brouillon').length,
      soumis: filteredTS.filter(ts => ts.statut_validation === 'soumis').length,
      confirmé: filteredTS.filter(ts => ts.statut_validation === 'confirmé').length,
      généré: filteredTS.filter(ts => ts.statut_validation === 'généré').length,
    },
  };

  // Grouper par professeur pour vue mensuelle
  const groupedByProf = {};
  filteredTS.forEach((ts) => {
    const profName = ts.professeur?.prenom + ' ' + ts.professeur?.nom || 'Inconnu';
    if (!groupedByProf[profName]) {
      groupedByProf[profName] = [];
    }
    groupedByProf[profName].push(ts);
  });

  // Historique des générations de ce mois
  const monthHistory = pdfGenerationHistory.filter((h) => {
    const hDate = new Date(h.created_at);
    return hDate.getFullYear() === year && hDate.getMonth() === month - 1;
  });

  return (
    <>
      <AdminPageHeader
        icon="📊"
        title="Gestion des Timesheets"
        description="Visualisez et gérez les timesheets par mois et par professeur"
        badge={`${filteredTS.length} entrées`}
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

        {/* Contrôles */}
        <div style={{
          background: 'white',
          borderRadius: '8px',
          border: `1px solid ${ADMIN_COLORS.border}`,
          padding: '20px',
          marginBottom: '24px',
        }}>
          <div style={{
            display: 'grid',
            gridTemplateColumns: 'auto 1fr auto auto',
            gap: '16px',
            alignItems: 'center',
          }}>
            {/* Sélection du mois */}
            <div>
              <label style={{
                display: 'block',
                fontSize: '12px',
                fontWeight: '600',
                color: '#6b7280',
                marginBottom: '6px',
                textTransform: 'uppercase',
                letterSpacing: '0.5px',
              }}>
                Mois
              </label>
              <input
                type="month"
                value={selectedMonth}
                onChange={(e) => setSelectedMonth(e.target.value)}
                style={{
                  padding: '8px 12px',
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '1em',
                }}
              />
            </div>

            {/* Sélection du professeur */}
            <div>
              <label style={{
                display: 'block',
                fontSize: '12px',
                fontWeight: '600',
                color: '#6b7280',
                marginBottom: '6px',
                textTransform: 'uppercase',
                letterSpacing: '0.5px',
              }}>
                Professeur
              </label>
              <select
                value={selectedProfesseur || ''}
                onChange={(e) => setSelectedProfesseur(e.target.value ? parseInt(e.target.value) : null)}
                style={{
                  padding: '8px 12px',
                  border: `1px solid ${ADMIN_COLORS.border}`,
                  borderRadius: '6px',
                  fontSize: '1em',
                  minWidth: '200px',
                }}
              >
                <option value="">Tous les professeurs</option>
                {professeurs.map((prof) => (
                  <option key={prof.id} value={prof.id}>
                    {prof.prenom} {prof.nom}
                  </option>
                ))}
              </select>
            </div>

            {/* Mode de vue */}
            <div style={{ display: 'flex', gap: '8px' }}>
              <AdminButton
                variant={viewMode === 'monthly' ? 'primary' : 'secondary'}
                size="sm"
                onClick={() => setViewMode('monthly')}
              >
                📅 Mois
              </AdminButton>
              <AdminButton
                variant={viewMode === 'professor' ? 'primary' : 'secondary'}
                size="sm"
                onClick={() => setViewMode('professor')}
              >
                👨‍🏫 Profs
              </AdminButton>
            </div>

            {/* Rafraîchir */}
            <AdminButton
              variant="secondary"
              size="sm"
              icon="🔄"
              onClick={loadData}
            >
              Rafraîchir
            </AdminButton>
          </div>
        </div>

        {/* Statistiques */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
          gap: '16px',
          marginBottom: '24px',
        }}>
          <StatCard
            title="Total Heures"
            value={`${stats.totalHeures.toFixed(1)}h`}
            icon="⏱️"
            color="#3b82f6"
          />
          <StatCard
            title="Coût Total"
            value={`${stats.totalMontant.toFixed(2)}€`}
            icon="💰"
            color="#10b981"
          />
          <StatCard
            title="À Traiter"
            value={`${stats.byStatus.brouillon + stats.byStatus.soumis}`}
            icon="📝"
            color="#f59e0b"
          />
          <StatCard
            title="Validés"
            value={`${stats.byStatus.confirmé}`}
            icon="✓"
            color="#8b5cf6"
          />
          <StatCard
            title="Générés"
            value={`${stats.byStatus.généré}`}
            icon="📄"
            color="#059669"
          />
        </div>

        {/* Vue par Mois (tableau consolidé) */}
        {viewMode === 'monthly' && (
          <>
            <div style={{
              background: 'white',
              borderRadius: '8px',
              border: `1px solid ${ADMIN_COLORS.border}`,
              overflow: 'hidden',
              marginBottom: '24px',
            }}>
              <div style={{
                background: '#f9fafb',
                padding: '16px',
                borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                fontWeight: '600',
              }}>
                📅 Vue Mensuelle - Récapitulatif par Professeur
              </div>
              <table style={{
                width: '100%',
                borderCollapse: 'collapse',
              }}>
                <thead>
                  <tr style={{ background: '#f9fafb', borderBottom: `1px solid ${ADMIN_COLORS.border}` }}>
                    <th style={thStyle}>Professeur</th>
                    <th style={thStyle}>Heures</th>
                    <th style={thStyle}>Montant</th>
                    <th style={thStyle}>Brouillon</th>
                    <th style={thStyle}>Soumis</th>
                    <th style={thStyle}>Confirmé</th>
                    <th style={thStyle}>Généré</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(groupedByProf).map(([profName, entries]) => {
                    const profHeures = entries.reduce((sum, ts) => sum + parseFloat(ts.nombre_heures || 0), 0);
                    const prof = entries[0].professeur;
                    const tarif = prof?.tarif_effectif?.tarif_horaire_eur || 0;
                    const profMontant = profHeures * parseFloat(tarif);
                    const statusCount = {
                      brouillon: entries.filter(e => e.statut_validation === 'brouillon').length,
                      soumis: entries.filter(e => e.statut_validation === 'soumis').length,
                      confirmé: entries.filter(e => e.statut_validation === 'confirmé').length,
                      généré: entries.filter(e => e.statut_validation === 'généré').length,
                    };

                    return (
                      <tr
                        key={profName}
                        style={{ borderBottom: `1px solid ${ADMIN_COLORS.border}` }}
                      >
                        <td style={tdStyle}>{profName}</td>
                        <td style={tdStyle}><strong>{profHeures.toFixed(1)}h</strong></td>
                        <td style={{ ...tdStyle, color: '#059669', fontWeight: '600' }}>
                          {profMontant.toFixed(2)}€
                        </td>
                        <td style={tdStyle}>{statusCount.brouillon}</td>
                        <td style={tdStyle}>{statusCount.soumis}</td>
                        <td style={tdStyle}>{statusCount.confirmé}</td>
                        <td style={tdStyle}>{statusCount.généré}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </>
        )}

        {/* Vue Détaillée */}
        <div style={{
          background: 'white',
          borderRadius: '8px',
          border: `1px solid ${ADMIN_COLORS.border}`,
          overflow: 'hidden',
        }}>
          <div style={{
            background: '#f9fafb',
            padding: '16px',
            borderBottom: `1px solid ${ADMIN_COLORS.border}`,
            fontWeight: '600',
          }}>
            📋 Détail des Timesheets ({filteredTS.length})
          </div>
          <table style={{
            width: '100%',
            borderCollapse: 'collapse',
          }}>
            <thead>
              <tr style={{ background: '#f9fafb', borderBottom: `1px solid ${ADMIN_COLORS.border}` }}>
                <th style={thStyle}>Professeur</th>
                <th style={thStyle}>Date</th>
                <th style={thStyle}>Heures</th>
                <th style={thStyle}>Tarif</th>
                <th style={thStyle}>Montant</th>
                <th style={thStyle}>Statut</th>
              </tr>
            </thead>
            <tbody>
              {filteredTS.length === 0 ? (
                <tr>
                  <td colSpan="6" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                    Aucun timesheet pour cette période
                  </td>
                </tr>
              ) : (
                filteredTS.map((ts, idx) => {
                  const prof = professeurs.find(p => p.id === ts.professeur_id);
                  const tarif = prof?.tarif_effectif?.tarif_horaire_eur || 0;
                  const montant = parseFloat(ts.nombre_heures || 0) * parseFloat(tarif);

                  return (
                    <tr
                      key={ts.id}
                      style={{
                        background: idx % 2 === 0 ? 'white' : '#f9fafb',
                        borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                      }}
                    >
                      <td style={tdStyle}>{ts.professeur?.prenom} {ts.professeur?.nom}</td>
                      <td style={tdStyle}>{ts.date_prestation?.slice(0, 10)}</td>
                      <td style={tdStyle}><strong>{ts.nombre_heures}h</strong></td>
                      <td style={tdStyle}>{parseFloat(tarif).toFixed(2)}€/h</td>
                      <td style={{ ...tdStyle, fontWeight: '600', color: '#059669' }}>
                        {montant.toFixed(2)}€
                      </td>
                      <td style={tdStyle}>
                        <AdminBadge
                          label={STATUT_LABELS[ts.statut_validation]}
                          color={STATUT_COLORS[ts.statut_validation]}
                        />
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Historique des générations PDF */}
        {monthHistory.length > 0 && (
          <div style={{
            marginTop: '24px',
            background: '#f0fdf4',
            border: `1px solid #86efac`,
            borderRadius: '8px',
            padding: '16px',
          }}>
            <h4 style={{ margin: '0 0 12px 0', color: '#15803d' }}>
              📄 Générations PDF du mois
            </h4>
            <div style={{ display: 'grid', gap: '8px' }}>
              {monthHistory.map((h, idx) => (
                <div key={idx} style={{
                  background: 'white',
                  padding: '12px',
                  borderRadius: '6px',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}>
                  <span>
                    {h.professeur_name} — {new Date(h.created_at).toLocaleString('fr-FR')}
                  </span>
                  <AdminButton
                    variant="secondary"
                    size="sm"
                    icon="⬇️"
                    onClick={() => downloadPDF(h.pdf_path)}
                  >
                    Télécharger
                  </AdminButton>
                </div>
              ))}
            </div>
          </div>
        )}
      </AdminPageContent>
    </>
  );
}

// Composants auxiliaires
function StatCard({ title, value, icon, color }) {
  return (
    <div style={{
      background: 'white',
      borderRadius: '8px',
      border: `1px solid ${ADMIN_COLORS.border}`,
      padding: '20px',
      boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
    }}>
      <div style={{
        fontSize: '24px',
        marginBottom: '8px',
      }}>
        {icon}
      </div>
      <div style={{
        fontSize: '12px',
        fontWeight: '600',
        color: '#6b7280',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        marginBottom: '8px',
      }}>
        {title}
      </div>
      <div style={{
        fontSize: '28px',
        fontWeight: 'bold',
        color: color,
      }}>
        {value}
      </div>
    </div>
  );
}

function downloadPDF(path) {
  window.location.href = `/api/timesheets/download-pdf?path=${encodeURIComponent(path)}`;
}

// Styles
const thStyle = {
  padding: '16px',
  textAlign: 'left',
  fontSize: '12px',
  fontWeight: '600',
  color: ADMIN_COLORS.textPrimary,
  textTransform: 'uppercase',
  letterSpacing: '0.5px',
};

const tdStyle = {
  padding: '16px',
  fontSize: '14px',
  color: ADMIN_COLORS.textPrimary,
};
