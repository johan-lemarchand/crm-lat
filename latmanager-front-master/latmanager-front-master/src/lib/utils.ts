import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";
import { format } from 'date-fns';
import { toast } from '@/components/ui/use-toast';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatFileSize(bytes: number): string {
  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }

  return `${size.toFixed(1)} ${units[unitIndex]}`;
}

export function formatDate(date: Date): string {
  return format(date, "dd/MM/yyyy HH:mm:ss");
}

/**
 * Décode une chaîne encodée en base64url.
 * @param str - La chaîne base64url à décoder
 * @returns La chaîne décodée
 */
export function base64UrlDecode(str: string): string {
  // Remplacer les caractères spéciaux de base64url par ceux de base64 standard
  str = str.replace(/-/g, '+').replace(/_/g, '/');
  
  // Ajouter le padding nécessaire
  while (str.length % 4) {
    str += '=';
  }
  
  return atob(str);
}

/**
 * Décode un token JWT et retourne les données (payload) sous forme d'objet.
 * @param token - Le token JWT à décoder
 * @returns L'objet contenant les données du token ou null en cas d'erreur
 */
export function decodeToken(token: string): Record<string, any> | null {
  try {
    // Séparer le token et prendre la partie data (avant le .)
    const [data] = token.split('.');
    
    // Décoder de base64url à chaîne JSON
    const jsonString = base64UrlDecode(data);
    
    // Parser la chaîne JSON en objet
    return JSON.parse(jsonString);
  } catch (error) {
    console.error('Erreur lors du décodage du token:', error);
    return null;
  }
}

/**
 * Version améliorée qui gère correctement les caractères Unicode.
 * Décode un token JWT et retourne les données (payload) sous forme d'objet.
 * @param token - Le token JWT à décoder
 * @returns L'objet contenant les données du token ou null en cas d'erreur
 */
export function decodeTokenUnicode(token: string): Record<string, any> | null {
  try {
    // Séparer le token et prendre la partie data (avant le .)
    const [data] = token.split('.');
    
    // Décoder de base64url à JSON avec support Unicode
    const base64Url = data.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(
      atob(base64Url)
        .split('')
        .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    );
    
    return JSON.parse(jsonPayload);
  } catch (error) {
    console.error('Erreur lors du décodage du token (Unicode):', error);
    return null;
  }
}

/**
 * Copie un texte dans le presse-papier et affiche une notification
 */
export const copyToClipboard = (text: string) => {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text)
      .then(() => {
        toast({
          variant: "success",
          title: "Copié !",
          description: "Le texte a été copié dans le presse-papier",
        });
      })
      .catch(err => {
        console.error("Erreur lors de la copie via Clipboard API :", err);
        fallbackCopyTextToClipboard(text);
      });
  } else {
    fallbackCopyTextToClipboard(text);
  }
};

/**
 * Méthode alternative pour copier du texte quand l'API Clipboard n'est pas disponible
 */
const fallbackCopyTextToClipboard = (text: string) => {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed"; // Empêche le scroll du viewport
  textArea.style.opacity = "0"; // Rend invisible

  document.body.appendChild(textArea);
  textArea.select();

  try {
    const successful = document.execCommand("copy");
    if (successful) {
      toast({
        variant: "success",
        title: "Copié !",
        description: "Le texte a été copié dans le presse-papier",
      });
    } else {
      throw new Error("Échec de la copie");
    }
  } catch (err) {
    console.error("Erreur lors de la copie via execCommand :", err);
    toast({
      title: "Erreur",
      description: "Impossible de copier le texte",
      variant: "destructive",
    });
  }
  document.body.removeChild(textArea);
};

interface MailtoInfo {
    formattedMessage: string;
    mailtoUrl: string;
    showMailto: boolean;
}

/**
 * Formate un message d'erreur avec un lien mailto si nécessaire
 * @param message - Le message d'erreur à formater
 * @param options - Options de formatage
 * @returns Un objet contenant le message formaté et les informations mailto
 */
export function formatErrorMessageWithMailto(
    message: string,
    options: {
        email?: string;
        subjectPrefix?: string;
    } = {}
): MailtoInfo {
    const {
        email = 'informatique@latitudegps.com',
        subjectPrefix = 'Erreur'
    } = options;

    // Extraire l'ID si présent
    const idMatch = message.match(/ID: (\d+)/);
    const id = idMatch ? idMatch[1] : '';

    // Construire le sujet de l'email
    const subject = `${subjectPrefix}${id ? ` - ID: ${id}` : ''}`;
    const emailSubject = encodeURIComponent(subject);

    // Construire le corps de l'email
    const emailBody = encodeURIComponent(`Bonjour,

Je rencontre une erreur lors du traitement automatique.

${id ? `ID: ${id}\n` : ''}
Message complet: ${message}

Cordialement`);

    const mailtoUrl = `mailto:${email}?subject=${emailSubject}&body=${emailBody}`;

    // Vérifier si le message contient l'email
    const hasEmail = message.includes(email);

    return {
        formattedMessage: message,
        mailtoUrl,
        showMailto: hasEmail
    };
}
