import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import client from '../api/client';
import { getErrorMessage } from '../api/errors';
import { useAuth } from '../auth/AuthContext';
import AdminButton from '../components/AdminButton';
import {
  AdminPageHeader,
  AdminPageContent,
} from '../components/AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';
import ProfesseurTariffForm from '../components/ProfesseurTariffForm';
import CreerProfesseurModal from '../components/professeurs/CreerProfesseurModal';
import StatutBadge from '../components/ui/StatutBadge';
import LienCopiable from '../components/ui/LienCopiable';
import { STATUTS_ACCES } from '../utils/statuts';
import { A_RELANCER_RAPIDE } from '../utils/acces';
import { FilterToolbar, ResultCount } from '../components/ui/Filters';
import { EmptyBlock } from '../components/ui/DataStates';
import { useFiltresListe } from '../hooks/useFiltresListe';
import {
  OPTIONS_ACCES, OPTIONS_CONTRAT, OPTIONS_STATUT, STATUT_PAR_DEFAUT, correspondAcces, correspondContrat,
  correspondRecherche, libelleResultats, statutPourApi,
} from '../utils/filtres';

const DEFAUTS = { q: '', statut: STATUT_PAR_DEFAUT, contrat: '', acces: '' };
const AUTORISEES = {
  statut: OPTIONS_STATUT.map((o) => o.value),
  contrat: OPTIONS_CONTRAT.map((o) => o.value),
  acces: OPTIONS_ACCES.map((o) => o.value),
};
const ID_RECHERCHE = 'professeurs-recherche';

export default function AdminProfesseursPage() {
  const navigate = useNavigate();
  const peutModifierTarifs = ['admin', 'directeur'].includes(useAuth().user?.role);
  const [professeurs, setProfesseurs] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [selectedProf, setSelectedProf] = useState(null);
  const [showTarifForm, setShowTarifForm] = useState(false);
  const [tariffs, setTariffs] = useState({});
  const [editingTariff, setEditingTariff] = useState(null);
  const { valeurs, majFiltre, reinitialiser, nbActifs, estParDefaut } = useFiltresListe(DEFAUTS, AUTORISEES);
  const [showCreer, setShowCreer] = useState(false);
  const [statutCharge, setStatutCharge] = useState(null); // statut pour lequel `professeurs` est chargé
  const [lienRepli, setLienRepli] = useState(null);
  const [relanceId, setRelanceId] = useState(null);

  // Seul le statut est filtré par le serveur : les autres filtres (recherche, contrat, accès) sont instantanés.
  useEffect(() => {
    loadData();
  }, [valeurs.statut]);

  async function loadData() {
    try {
      const statutDemande = valeurs.statut;
      const profsRes = await client.get('/professeurs', { params: statutDemande === 'tous' ? {} : { statut: statutPourApi(statutDemande) } });
      setProfesseurs(profsRes.data);
      setStatutCharge(statutDemande);

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

  // Relance rapide depuis la carte (invitation expirée ou non envoyée).
  async function relancer(prof) {
    setRelanceId(prof.id);
    setError(null);
    setSuccess(null);
    setLienRepli(null);
    try {
      const res = await client.post(`/professeurs/${prof.id}/envoyer-lien`);
      if (res.data.mail_envoye) {
        setSuccess(`Invitation envoyée à ${prof.user?.email}.`);
        setTimeout(() => setSuccess(null), 4000);
      } else {
        setError(`L'email destiné à ${prof.user?.email} n'a pas pu être envoyé. Réessayez, ou transmettez le lien ci-dessous.`);
        setLienRepli(res.data.lien);
      }
      loadData();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setRelanceId(null);
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
    } catch (err) {
      setError(getErrorMessage(err, 'Erreur lors de la suppression'));
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

  const aRelancer = professeurs.filter((p) => p.statut === 'actif' && p.acces?.a_relancer).length;
  const affiches = professeurs.filter((p) => correspondAcces(p.acces, valeurs.acces)
    && correspondContrat(p.type_contrat, valeurs.contrat)
    && correspondRecherche([p.prenom, p.nom, p.email, p.user?.email], valeurs.q));
  // Vide « pour de vrai » seulement si les données correspondent au statut affiché (évite de démonter la barre).
  const listeReellementVide = professeurs.length === 0 && estParDefaut && statutCharge === valeurs.statut;

  function reinitialiserEtFocaliser() {
    reinitialiser();
    document.getElementById(ID_RECHERCHE)?.focus();
  }

  return (
    <>
      <AdminPageHeader
        icon="👨‍🏫"
        title="Gestion des Professeurs & Tarifs"
        description="Gérez vos professeurs, leurs comptes et leurs tarifs horaires"
        action={
          <AdminButton variant="primary" icon="➕" onClick={() => setShowCreer(true)}>
            Nouveau professeur
          </AdminButton>
        }
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
            {lienRepli && <LienCopiable lien={lienRepli} />}
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

        {listeReellementVide ? (
          <EmptyBlock icon="👨‍🏫" title="Aucun professeur actif">
            Créez un professeur avec « Nouveau professeur » pour commencer.
          </EmptyBlock>
        ) : (
          <>
            <FilterToolbar
              label="Filtres des professeurs"
              recherche={{ id: ID_RECHERCHE, value: valeurs.q, onChange: (v) => majFiltre('q', v), placeholder: 'Nom ou email' }}
              filtres={[
                { id: 'professeurs-statut', label: 'Statut', value: valeurs.statut, onChange: (v) => majFiltre('statut', v), options: OPTIONS_STATUT },
                { id: 'professeurs-contrat', label: 'Contrat', value: valeurs.contrat, onChange: (v) => majFiltre('contrat', v), options: OPTIONS_CONTRAT, placeholder: 'Tous' },
                { id: 'professeurs-acces', label: 'Accès', value: valeurs.acces, onChange: (v) => majFiltre('acces', v), options: OPTIONS_ACCES, placeholder: 'Tous' },
              ]}
              nbActifs={nbActifs}
              onReset={reinitialiser}
              resetDisabled={estParDefaut}
            />
            <ResultCount>{libelleResultats(affiches.length, professeurs.length, ['professeur', 'professeurs'])}</ResultCount>
            {aRelancer > 0 && valeurs.statut !== 'inactif' && valeurs.acces !== 'relancer' && (
              <p style={{ margin: '0 0 12px', fontSize: '14px' }}>
                {aRelancer} invitation{aRelancer > 1 ? 's' : ''} à relancer.{' '}
                <button
                  type="button"
                  onClick={() => majFiltre('acces', 'relancer')}
                  style={{ background: 'none', border: 'none', padding: 0, color: '#1d4ed8', textDecoration: 'underline', cursor: 'pointer', fontWeight: 400 }}
                >
                  Les afficher
                </button>
              </p>
            )}
            {affiches.length === 0 && (
              <EmptyBlock
                icon="🔎"
                title="Aucun professeur ne correspond à ces filtres"
                actions={(
                  <>
                    <AdminButton variant="primary" onClick={reinitialiserEtFocaliser}>Réinitialiser les filtres</AdminButton>
                    {valeurs.statut === STATUT_PAR_DEFAUT && (
                      <AdminButton variant="secondary" onClick={() => majFiltre('statut', 'tous')}>Inclure les désactivés</AdminButton>
                    )}
                  </>
                )}
              >
                Modifiez la recherche ou les filtres pour afficher des professeurs.
              </EmptyBlock>
            )}
          </>
        )}

        {/* Liste des professeurs */}
        <div style={{ display: 'grid', gap: '24px' }}>
          {affiches.map((prof) => {
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
                      {prof.prenom} {prof.nom}{' '}
                      <span style={{ fontSize: '12px', fontWeight: 600, color: prof.statut === 'actif' ? '#047857' : '#6b7280' }}>
                        {prof.statut === 'actif' ? 'Actif' : 'Désactivé'}
                      </span>
                    </h3>
                    <p style={{ margin: 0, color: '#6b7280', fontSize: '0.9em' }}>
                      {prof.email}
                    </p>
                    {prof.statut === 'actif' && prof.acces?.statut && (
                      <p style={{ margin: '8px 0 0' }}>
                        <StatutBadge table={STATUTS_ACCES} valeur={prof.acces.statut} />
                        {A_RELANCER_RAPIDE.includes(prof.acces.statut) && (
                          <AdminButton
                            variant="secondary"
                            size="sm"
                            style={{ marginLeft: '8px' }}
                            loading={relanceId === prof.id}
                            onClick={() => relancer(prof)}
                          >
                            Renvoyer
                          </AdminButton>
                        )}
                      </p>
                    )}
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
                    {peutModifierTarifs && (
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
                    )}
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
                            {peutModifierTarifs && (
                            <div style={{ display: 'flex', gap: '8px' }}>
                              <AdminButton
                                variant="secondary"
                                size="sm"
                                icon="✏️"
                                aria-label="Modifier le tarif"
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
                                aria-label="Supprimer le tarif"
                                onClick={() => handleDeleteTariff(prof.id, tariff.id)}
                              >
                              </AdminButton>
                            </div>
                            )}
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

        {showCreer && (
          <CreerProfesseurModal
            onClose={() => setShowCreer(false)}
            onCreated={() => loadData()}
            onVoirFiche={(professeur) => navigate(`/admin/professeurs/${professeur.id}`)}
          />
        )}

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
