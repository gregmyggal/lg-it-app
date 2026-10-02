import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import client from '../api/client';
import TimesheetsParSession from '../components/timesheets/TimesheetsParSession';
import DetailProfesseurMois from '../components/timesheets/DetailProfesseurMois';
import SyntheseValidationMois from '../components/timesheets/SyntheseValidationMois';
import AdapterSaisieModal from '../components/timesheets/AdapterSaisieModal';
import { useToast } from '../hooks/useToast';
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
  confirme: 'Confirmé',
  conteste: 'Contesté',
  genere: 'Généré',
};

const STATUT_COLORS = {
  brouillon: 'amber',
  soumis: 'blue',
  confirme: 'indigo',
  conteste: 'red',
  genere: 'green',
};

export default function AdminTimesheetsPage() {
  const { user } = useAuth();
  const [timesheets, setTimesheets] = useState(null);
  const [error, setError] = useState(null);
  const [selectedMonth, setSelectedMonth] = useState(new Date().toISOString().slice(0, 7));
  const [selectedProfesseur, setSelectedProfesseur] = useState(null);
  const [viewMode, setViewMode] = useState('validation'); // 'validation' (TS-01 T2), 'monthly', 'professor' ou 'session' (CLS-01 T3)
  const [professeurs, setProfesseurs] = useState([]);
  const [pdfGenerationHistory, setPdfGenerationHistory] = useState([]);
  const [saisieAAdapter, setSaisieAAdapter] = useState(null);
  const [params] = useSearchParams();
  const toast = useToast();

  useEffect(() => {
    loadData();
  }, []);

  // Lien d'une notification (« conteste ses heures ») : ouvre directement le détail du professeur et du mois.
  useEffect(() => {
    const professeur = Number(params.get('professeur'));
    const mois = params.get('mois');
    if (professeur && /^\d{4}-\d{2}$/.test(mois || '')) {
      setSelectedProfesseur(professeur);
      setSelectedMonth(mois);
      setViewMode('detail');
    }
  }, [params]);

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
      <div style={{ padding: '24px', textAlign: 'center', color: 'var(--c-text-2)' }}>
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
    // Montants calculés par le serveur (heures × tarif en vigueur à la date de chaque saisie).
    totalMontant: filteredTS.reduce((sum, ts) => sum + (parseFloat(ts.montant_brut) || 0), 0),
    byStatus: {
      brouillon: filteredTS.filter(ts => ts.statut_validation === 'brouillon').length,
      soumis: filteredTS.filter(ts => ts.statut_validation === 'soumis').length,
      confirme: filteredTS.filter(ts => ts.statut_validation === 'confirme').length,
      genere: filteredTS.filter(ts => ts.statut_validation === 'genere').length,
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
            background: 'var(--tone-error-bg)',
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
          background: 'var(--c-card)',
          borderRadius: '8px',
          border: `1px solid ${ADMIN_COLORS.border}`,
          padding: '20px',
          marginBottom: '24px',
        }}>
          <div style={{
            display: 'flex',
            flexWrap: 'wrap',
            gap: '16px',
            alignItems: 'flex-end',
          }}>
            {/* Sélection du mois */}
            <div>
              <label style={{
                display: 'block',
                fontSize: '12px',
                fontWeight: '600',
                color: 'var(--c-text-2)',
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
                color: 'var(--c-text-2)',
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
                variant={viewMode === 'validation' ? 'primary' : 'secondary'}
                size="sm"
                onClick={() => setViewMode('validation')}
              >
                ✅ Validation
              </AdminButton>
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
              <AdminButton
                variant={viewMode === 'session' ? 'primary' : 'secondary'}
                size="sm"
                onClick={() => setViewMode('session')}
              >
                🏫 Sessions
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

        {viewMode === 'validation' && (
          <SyntheseValidationMois
            mois={selectedMonth}
            onMoisChange={setSelectedMonth}
            onChange={loadData}
            onOuvrir={(id) => {
              setSelectedProfesseur(id);
              setViewMode('detail');
            }}
          />
        )}

        {viewMode === 'detail' && selectedProfesseur && (
          <DetailProfesseurMois
            professeurId={selectedProfesseur}
            mois={selectedMonth}
            onRetour={() => {
              setSelectedProfesseur(null);
              setViewMode('validation');
            }}
            onChange={loadData}
          />
        )}

        {viewMode === 'session' && <TimesheetsParSession mois={selectedMonth} onChange={loadData} />}

        {viewMode !== 'session' && viewMode !== 'validation' && viewMode !== 'detail' && (
          <>
        {/* Statistiques */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(clamp(180px, 14vw, 260px), 1fr))',
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
            value={`${stats.byStatus.confirme}`}
            icon="✓"
            color="#8b5cf6"
          />
          <StatCard
            title="Générés"
            value={`${stats.byStatus.genere}`}
            icon="📄"
            color="#059669"
          />
        </div>
          </>
        )}

        {/* Vue par Mois (tableau consolidé) */}
        {viewMode === 'monthly' && (
          <>
            <div style={{
              background: 'var(--c-card)',
              borderRadius: '8px',
              border: `1px solid ${ADMIN_COLORS.border}`,
              overflow: 'hidden',
              marginBottom: '24px',
            }}>
              <div style={{
                background: 'var(--c-bg)',
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
                  <tr style={{ background: 'var(--c-bg)', borderBottom: `1px solid ${ADMIN_COLORS.border}` }}>
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
                    const profMontant = entries.reduce((sum, ts) => sum + (parseFloat(ts.montant_brut) || 0), 0);
                    const statusCount = {
                      brouillon: entries.filter(e => e.statut_validation === 'brouillon').length,
                      soumis: entries.filter(e => e.statut_validation === 'soumis').length,
                      confirme: entries.filter(e => e.statut_validation === 'confirme').length,
                      genere: entries.filter(e => e.statut_validation === 'genere').length,
                    };

                    return (
                      <tr
                        key={profName}
                        style={{ borderBottom: `1px solid ${ADMIN_COLORS.border}` }}
                      >
                        <td style={tdStyle}>{profName}</td>
                        <td style={tdStyle}><strong>{profHeures.toFixed(1)}h</strong></td>
                        <td style={{ ...tdStyle, color: 'var(--c-success)', fontWeight: '600' }}>
                          {profMontant.toFixed(2)}€
                        </td>
                        <td style={tdStyle}>{statusCount.brouillon}</td>
                        <td style={tdStyle}>{statusCount.soumis}</td>
                        <td style={tdStyle}>{statusCount.confirme}</td>
                        <td style={tdStyle}>{statusCount.genere}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </>
        )}

        {/* Vue Détaillée (masquée dans la vue par session, qui a ses propres tableaux) */}
        {viewMode !== 'session' && viewMode !== 'validation' && viewMode !== 'detail' && (
        <div style={{
          background: 'var(--c-card)',
          borderRadius: '8px',
          border: `1px solid ${ADMIN_COLORS.border}`,
          overflow: 'hidden',
        }}>
          <div style={{
            background: 'var(--c-bg)',
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
              <tr style={{ background: 'var(--c-bg)', borderBottom: `1px solid ${ADMIN_COLORS.border}` }}>
                <th style={thStyle}>Professeur</th>
                <th style={thStyle}>Date</th>
                <th style={thStyle}>Heures</th>
                <th style={thStyle}>Tarif</th>
                <th style={thStyle}>Montant</th>
                <th style={thStyle}>Statut</th>
                <th style={thStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredTS.length === 0 ? (
                <tr>
                  <td colSpan="7" style={{ padding: '40px', textAlign: 'center', color: 'var(--c-text-3)' }}>
                    Aucun timesheet pour cette période
                  </td>
                </tr>
              ) : (
                filteredTS.map((ts, idx) => {
                  const montant = parseFloat(ts.montant_brut) || 0;
                  const heures = parseFloat(ts.nombre_heures || 0);
                  const tarif = heures > 0 ? montant / heures : 0;

                  return (
                    <tr
                      key={ts.id}
                      style={{
                        background: idx % 2 === 0 ? 'var(--c-card)' : 'var(--c-bg)',
                        borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                      }}
                    >
                      <td style={tdStyle}>{ts.professeur?.prenom} {ts.professeur?.nom}</td>
                      <td style={tdStyle}>{ts.date_prestation?.slice(0, 10)}</td>
                      <td style={tdStyle}><strong>{ts.nombre_heures}h</strong></td>
                      <td style={tdStyle}>{parseFloat(tarif).toFixed(2)}€/h</td>
                      <td style={{ ...tdStyle, fontWeight: '600', color: 'var(--c-success)' }}>
                        {montant.toFixed(2)}€
                      </td>
                      <td style={tdStyle}>
                        <AdminBadge
                          label={STATUT_LABELS[ts.statut_validation]}
                          color={STATUT_COLORS[ts.statut_validation]}
                        />
                      </td>
                      <td style={tdStyle}>
                        {ts.can?.adapt && (
                          <AdminButton variant="secondary" size="sm" onClick={() => setSaisieAAdapter(ts)}>
                            Adapter
                          </AdminButton>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
        )}

        {/* Historique des générations PDF */}
        {monthHistory.length > 0 && (
          <div style={{
            marginTop: '24px',
            background: 'var(--tone-success-bg)',
            border: `1px solid var(--tone-success-bd)`,
            borderRadius: '8px',
            padding: '16px',
          }}>
            <h4 style={{ margin: '0 0 12px 0', color: '#15803d' }}>
              📄 Générations PDF du mois
            </h4>
            <div style={{ display: 'grid', gap: '8px' }}>
              {monthHistory.map((h, idx) => (
                <div key={idx} style={{
                  background: 'var(--c-card)',
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
      {saisieAAdapter && (
        <AdapterSaisieModal
          saisie={saisieAAdapter}
          onClose={() => setSaisieAAdapter(null)}
          onDone={(message) => {
            setSaisieAAdapter(null);
            toast.success(message);
            loadData();
          }}
        />
      )}
    </>
  );
}

// Composants auxiliaires
function StatCard({ title, value, icon, color }) {
  return (
    <div style={{
      background: 'var(--c-card)',
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
        color: 'var(--c-text-2)',
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
