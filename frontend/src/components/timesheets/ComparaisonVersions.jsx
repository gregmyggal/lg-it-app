import { useState } from 'react';
import AdminButton from '../AdminButton';
import Banner from '../ui/Banner';
import { CoteACote, TableEcarts, TableVersion } from './VersionsMois';
import { useMediaQuery } from '../../hooks/useMediaQuery';
import { formatDateCourte } from '../../utils/dates';
import { formatEuros, formatHeures } from '../../utils/format';
import { ADMIN_SPACING } from '../../styles/AdminDesignSystem';

const TITRE_SOUMISE = "Ce que j'ai soumis";
const TITRE_VALIDEE = 'Version validée (à signer)';

/**
 * TS-04 (écran professeur, avant signature) : ce que j'ai soumis face à la version validée par la direction, avec les
 * motifs des changements. Seule la version validée se signe. Sur smartphone : Écarts (par défaut s'il y en a),
 * Validée, Soumise. Aucune empreinte ni jargon : le serveur ne les envoie pas au professeur.
 *
 * @param {object} props
 * @param {{soumise: object, validee: object, ecart: object}} props.versions réponse de `mes-versions`, comparable
 */
export default function ComparaisonVersions({ versions }) {
  const mobile = useMediaQuery('(max-width: 860px)');
  const { soumise, validee, ecart } = versions;
  const [vue, setVue] = useState(null);

  const defaut = ecart.identique ? 'validee' : mobile ? 'ecarts' : 'cote';
  const segments = mobile
    ? [['ecarts', 'Écarts'], ['validee', 'Validée'], ['soumise', 'Soumise']]
    : [['cote', 'Côte à côte'], ['ecarts', 'Écarts'], ['validee', 'Validée'], ['soumise', 'Soumise']];
  const courante = vue && segments.some(([c]) => c === vue) ? vue : defaut;
  const motifs = ecart.lignes.filter((l) => l.motif && l.etat !== 'identique');

  return (
    <div style={{ marginBottom: ADMIN_SPACING.lg }}>
      {ecart.identique && (
        <Banner tone="info">
          <strong>Identique à votre soumission.</strong> Aucun écart : la direction a validé votre mois tel que vous l'avez soumis ({formatHeures(validee.total_heures)} · {formatEuros(validee.total_eur)}).
        </Banner>
      )}

      <div role="group" aria-label="Affichage des versions" style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: ADMIN_SPACING.md }}>
        {segments.map(([cle, libelle]) => (
          <AdminButton
            key={cle}
            size="sm"
            variant={courante === cle ? 'primary' : 'secondary'}
            aria-pressed={courante === cle}
            style={{ minHeight: 44 }}
            onClick={() => setVue(cle)}
          >
            {libelle}
          </AdminButton>
        ))}
      </div>
      <p aria-live="polite" className="sr-only">Vue {segments.find(([c]) => c === courante)?.[1]} affichée</p>

      {courante === 'cote' && <CoteACote soumise={soumise} validee={validee} ecart={ecart} titreSoumise={TITRE_SOUMISE} titreValidee={TITRE_VALIDEE} />}
      {courante === 'ecarts' && (ecart.identique ? <p>Aucun écart à afficher.</p> : <TableEcarts ecart={ecart} />)}
      {courante === 'validee' && (
        <>
          <h4 style={{ margin: '0 0 8px' }}>{TITRE_VALIDEE}</h4>
          <TableVersion version={validee} titre={TITRE_VALIDEE} />
        </>
      )}
      {courante === 'soumise' && (
        <>
          <h4 style={{ margin: '0 0 8px' }}>{TITRE_SOUMISE}</h4>
          <TableVersion version={soumise} titre={TITRE_SOUMISE} />
        </>
      )}

      {motifs.length > 0 && (
        <section aria-label="Pourquoi ces changements ?" style={{ marginTop: ADMIN_SPACING.lg }}>
          <h4 style={{ margin: '0 0 4px' }}>Pourquoi ces changements ?</h4>
          <ul style={{ margin: 0, paddingLeft: 20, fontSize: 14 }}>
            {motifs.map((l) => (
              <li key={l.id}>{formatDateCourte((l.apres || l.avant).date)} : « {l.motif} »</li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
