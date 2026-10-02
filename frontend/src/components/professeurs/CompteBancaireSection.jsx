import { useEffect, useState } from 'react';
import client from '../../api/client';
import AdminButton from '../AdminButton';
import { AdminCard, AdminCardHeader, AdminCardBody } from '../AdminPageLayout';
import { AdminFormField, AdminInput } from '../AdminFormField';
import { useToast } from '../../hooks/useToast';
import { getErrorMessage, getFieldErrors } from '../../api/errors';

/** Regroupe l'IBAN par blocs de 4 pour l'affichage (« BE68 5390 0754 7034 »). */
function formaterIban(iban) {
  return (iban || '').replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim();
}

/**
 * Numéro de compte du professeur (imprimé sur la fiche de défraiement). Saisie par le directeur ou l'admin ;
 * le serveur valide et normalise l'IBAN (source de vérité).
 */
export default function CompteBancaireSection({ professeur, onSaved }) {
  const toast = useToast();
  const [valeur, setValeur] = useState(formaterIban(professeur.compte_bancaire));
  const [erreur, setErreur] = useState(null);
  const [envoi, setEnvoi] = useState(false);

  useEffect(() => {
    setValeur(formaterIban(professeur.compte_bancaire));
  }, [professeur.compte_bancaire]);

  const inchange = valeur.replace(/\s+/g, '') === (professeur.compte_bancaire || '');

  async function enregistrer(e) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    try {
      await client.put(`/professeurs/${professeur.id}`, { compte_bancaire: valeur.trim() || null });
      toast.success('Compte bancaire enregistré.');
      onSaved?.();
    } catch (err) {
      setErreur(getFieldErrors(err).compte_bancaire || getErrorMessage(err, 'Enregistrement impossible'));
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <AdminCard>
      <AdminCardHeader title="🏦 Compte bancaire" />
      <AdminCardBody>
        <form onSubmit={enregistrer} noValidate>
          <AdminFormField
            label="Numéro de compte (IBAN)"
            htmlFor="compte-bancaire"
            error={erreur}
            description={professeur.compte_bancaire ? null : 'Obligatoire pour générer la fiche de défraiement PDF.'}
          >
            <AdminInput
              id="compte-bancaire"
              value={valeur}
              placeholder="BE00 0000 0000 0000"
              onChange={(e) => setValeur(e.target.value)}
              autoComplete="off"
            />
          </AdminFormField>
          <AdminButton type="submit" disabled={envoi || inchange}>
            {envoi ? 'Enregistrement…' : 'Enregistrer'}
          </AdminButton>
        </form>
      </AdminCardBody>
    </AdminCard>
  );
}
