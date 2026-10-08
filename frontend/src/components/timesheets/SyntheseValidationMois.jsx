import { useMemo, useState } from 'react';
import AdminButton from '../AdminButton';
import StatutBadge from '../ui/StatutBadge';
import Banner from '../ui/Banner';
import { Table, Th, Td, Tr } from '../ui/Table';
import { FilterField, FilterSearch } from '../ui/Filters';
import { LoadingBlock, ErrorBlock, EmptyBlock } from '../ui/DataStates';
import ValiderLotModal from './ValiderLotModal';
import EmployeurBadge from '../employeurs/EmployeurBadge';
import { useEmployeurs } from '../../hooks/useEmployeurs';
import { genererPdfLot, useListeSaisies, useSyntheseMois } from '../../hooks/useTimesheets';
import { useToast } from '../../hooks/useToast';
import { STATUTS_MOIS_PROF } from '../../utils/statuts';
import { formatDateHeure } from '../../utils/dates';
import { enregistrerBlob } from '../../utils/telechargement';
import { getErrorData, getErrorMessage } from '../../api/errors';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_COLORS, ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const dernierJour = (annee, mois) => new Date(annee, mois, 0).getDate();
const OPTIONS_STATUT = Object.entries(STATUTS_MOIS_PROF).map(([value, s]) => ({ value, label: s.label }));

function exporterCsv(lignes, annee, mois) {
  const entete = ['Professeur', 'Employeur', 'Statut', 'Heures animation', 'Heures préparation', 'Total EUR', 'Lignes ajustées'];
  const corps = lignes.map((l) => [l.professeur, l.employeur?.employeur.nom, STATUTS_MOIS_PROF[l.statut_mois]?.label, l.heures_animation, l.heures_preparation, l.total_eur, l.lignes_ajustees]);
  const csv = [entete, ...corps].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(';')).join('\n');
  const url = URL.createObjectURL(new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = `timesheets-${annee}-${String(mois).padStart(2, '0')}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

/**
 * Validation du mois (TS-01 T2) : un professeur par ligne, statut du mois calculé par le serveur, totaux et alertes.
 * « Valider la sélection » réutilise la validation en lot existante (avec lissage) sur les saisies soumises des
 * professeurs cochés. Le serveur reste l'autorité (droits, statuts, atomicité).
 *
 * @param {object} props
 * @param {string} props.mois `YYYY-MM`
 * @param {(mois: string) => void} props.onMoisChange
 * @param {(professeurId: number) => void} props.onOuvrir ouvre le détail des saisies du professeur
 * @param {() => void} [props.onChange] rechargement des autres vues après validation
 */
export default function SyntheseValidationMois({ mois, onMoisChange, onOuvrir, onChange }) {
  const toast = useToast();
  const [annee, moisNum] = mois.split('-').map(Number);
  const synthese = useSyntheseMois(annee, moisNum);
  const saisies = useListeSaisies({ date_from: `${mois}-01`, date_to: `${mois}-${String(dernierJour(annee, moisNum)).padStart(2, '0')}` });
  const [recherche, setRecherche] = useState('');
  const [statut, setStatut] = useState('');
  const [employeurFiltre, setEmployeurFiltre] = useState('');
  const employeurs = useEmployeurs();
  const [selection, setSelection] = useState(new Set());
  const [lot, setLot] = useState(false);
  const [pdfEnCours, setPdfEnCours] = useState(false);
  const [bloquants, setBloquants] = useState(null);

  const lignes = useMemo(() => (synthese.data?.professeurs || []).filter((l) => (
    (!statut || l.statut_mois === statut)
    && (!recherche || l.professeur.toLowerCase().includes(recherche.toLowerCase()))
  )), [synthese.data, statut, recherche]);

  const saisiesChoisies = (saisies.data || []).filter((t) => selection.has(t.professeur_id) && t.statut_validation === 'soumis' && t.can?.validate);

  const choisis = lignes.filter((l) => selection.has(l.professeur_id));
  const pdfPossible = choisis.length > 0 && choisis.every((l) => l.statut_mois === 'pret_pdf');

  async function genererLot() {
    setPdfEnCours(true);
    setBloquants(null);
    try {
      const zip = await genererPdfLot(choisis.map((l) => l.professeur_id), annee, moisNum);
      enregistrerBlob(zip, `fiches-defraiement-${mois}.zip`);
      toast.success(`${choisis.length} fiche(s) générée(s).`);
      setSelection(new Set());
      synthese.reload();
      onChange?.();
    } catch (err) {
      const liste = getErrorData(err)?.bloquants;
      if (liste) setBloquants(liste);
      else toast.error(getErrorMessage(err, 'Génération impossible'));
    } finally {
      setPdfEnCours(false);
    }
  }

  function basculer(id) {
    setSelection((prev) => {
      const n = new Set(prev);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  }

  function decaler(delta) {
    const d = new Date(annee, moisNum - 1 + delta, 1);
    onMoisChange(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    setSelection(new Set());
  }

  function apresValidation(message) {
    setLot(false);
    setSelection(new Set());
    toast.success(message);
    synthese.reload();
    saisies.reload();
    onChange?.();
  }

  const kpis = synthese.data?.kpis;
  const libelleMois = new Date(annee, moisNum - 1, 1).toLocaleDateString('fr-BE', { month: 'long', year: 'numeric' });

  return (
    <>
      <div style={{ display: 'flex', alignItems: 'center', gap: ADMIN_SPACING.md, marginBottom: ADMIN_SPACING.lg }}>
        <AdminButton variant="secondary" size="sm" onClick={() => decaler(-1)} aria-label="Mois précédent">‹</AdminButton>
        <strong style={{ textTransform: 'capitalize', minWidth: 140, textAlign: 'center' }} aria-live="polite">{libelleMois}</strong>
        <AdminButton variant="secondary" size="sm" onClick={() => decaler(1)} aria-label="Mois suivant">›</AdminButton>
      </div>

      {synthese.loading && <LoadingBlock message="Chargement de la synthèse…" />}
      {synthese.error && <ErrorBlock message={synthese.error} onRetry={synthese.reload} />}

      {kpis && (
        <>
          <div className="kpi-grid" style={{ marginBottom: ADMIN_SPACING.lg }}>
            {[['Profs ayant soumis', kpis.soumis], ['À valider', kpis.a_valider], ['Attente professeur', kpis.attente_prof], ['Contestés', kpis.conteste], ['Prêts PDF', kpis.pret_pdf], ['Générés', kpis.generes]].map(([titre, v]) => (
              <div key={titre} style={{ border: `1px solid ${ADMIN_COLORS.border}`, borderRadius: 8, padding: 10, background: 'var(--c-card)' }}>
                <div style={{ fontSize: 22, fontWeight: 700 }}>{v}</div>
                <div style={{ fontSize: 12, color: 'var(--c-text-2)' }}>{titre}</div>
              </div>
            ))}
          </div>

          <div style={{ display: 'flex', flexWrap: 'wrap', gap: ADMIN_SPACING.md, alignItems: 'flex-end', marginBottom: ADMIN_SPACING.md }}>
            <FilterSearch id="synthese-recherche" value={recherche} onChange={setRecherche} placeholder="Nom du professeur" />
            <FilterField label="Statut" value={statut} onChange={setStatut} options={OPTIONS_STATUT} placeholder="Tous les statuts" />
            <FilterField
              label="Employeur"
              value={employeurFiltre}
              onChange={setEmployeurFiltre}
              options={(employeurs.data || []).map((e) => ({ value: String(e.id), label: e.nom }))}
              placeholder="Tous les employeurs"
            />
            <div style={{ marginLeft: 'auto', display: 'flex', gap: ADMIN_SPACING.sm }}>
              <AdminButton variant="secondary" onClick={() => exporterCsv(lignes, annee, moisNum)} disabled={lignes.length === 0}>Exporter CSV</AdminButton>
              <AdminButton
                variant="secondary"
                onClick={genererLot}
                disabled={!pdfPossible || pdfEnCours}
                title={choisis.length > 0 && !pdfPossible ? 'Tous les professeurs sélectionnés doivent être « Prêt PDF ».' : undefined}
              >
                {pdfEnCours ? 'Génération…' : `Générer les PDF (zip)${pdfPossible ? ` (${choisis.length})` : ''}`}
              </AdminButton>
              <AdminButton onClick={() => setLot(true)} disabled={saisiesChoisies.length === 0}>
                Valider la sélection ({saisiesChoisies.length > 0 ? selection.size : 0})
              </AdminButton>
            </div>
          </div>

          {bloquants && (
            <Banner tone="error" role="alert">
              <strong>Aucun PDF n’a été généré.</strong>
              <ul style={{ margin: '4px 0 0', paddingLeft: 18 }}>
                {bloquants.map((b) => <li key={b.professeur_id}>{b.professeur} : {b.raisons.join(', ')}</li>)}
              </ul>
            </Banner>
          )}
          {synthese.data.professeurs.length === 0 ? (
            <EmptyBlock icon="📭" title="Aucune timesheet pour ce mois">Aucun professeur n’a encodé ni soumis d’heures sur cette période.</EmptyBlock>
          ) : (
            <Table caption="Validation du mois par professeur" minWidth="980px">
              <thead>
                <tr>
                  <Th srOnly>Sélection</Th><Th>Professeur</Th><Th>Employeur</Th><Th>Statut du mois</Th><Th>H. animation</Th><Th xl>H. préparation</Th>
                  <Th>Total</Th><Th>Lignes ajustées</Th><Th>Alertes</Th><Th xl>Dernière action</Th><Th srOnly>Ouvrir</Th>
                </tr>
              </thead>
              <tbody>
                {lignes.map((l) => (
                  <Tr key={l.professeur_id}>
                    <Td>
                      <input
                        type="checkbox"
                        aria-label={`Sélectionner ${l.professeur}`}
                        checked={selection.has(l.professeur_id)}
                        disabled={l.lignes_soumises === 0 && l.statut_mois !== 'pret_pdf'}
                        onChange={() => basculer(l.professeur_id)}
                      />
                    </Td>
                    <Td>{l.professeur}</Td>
                    <Td>{l.employeur && <EmployeurBadge employeur={l.employeur.employeur} source={l.employeur.source} verrouille={l.employeur.verrouille} raisonVerrou={l.employeur.raison_verrou} />}</Td>
                    <Td><StatutBadge table={STATUTS_MOIS_PROF} valeur={l.statut_mois} /></Td>
                    <Td>{l.heures_animation ? formatHeures(l.heures_animation) : '—'}</Td>
                    <Td xl>{l.heures_preparation ? formatHeures(l.heures_preparation) : '—'}</Td>
                    <Td>{l.lignes ? formatEuros(l.total_eur) : '—'}</Td>
                    <Td>{l.lignes_ajustees || '—'}</Td>
                    <Td>
                      {l.alertes.length === 0 ? '—' : l.alertes.map((a) => (
                        <div key={a.code} style={{ color: 'var(--tone-warning-fg)', fontSize: 12 }}>⚠ {a.libelle}</div>
                      ))}
                    </Td>
                    <Td xl>{l.derniere_action_at ? formatDateHeure(l.derniere_action_at) : '—'}</Td>
                    <Td><AdminButton variant="secondary" size="sm" onClick={() => onOuvrir(l.professeur_id)}>Ouvrir</AdminButton></Td>
                  </Tr>
                ))}
              </tbody>
            </Table>
          )}
          {synthese.data.professeurs.length > 0 && lignes.length === 0 && (
            <Banner tone="info">Aucun professeur ne correspond aux filtres.</Banner>
          )}
        </>
      )}

      {lot && synthese.data && (
        <ValiderLotModal
          plafond={synthese.data.periode.plafond_journalier_eur}
          selection={saisiesChoisies}
          toutesLesSaisies={saisies.data || []}
          annee={annee}
          mois={moisNum}
          onClose={() => setLot(false)}
          onDone={apresValidation}
        />
      )}
    </>
  );
}
