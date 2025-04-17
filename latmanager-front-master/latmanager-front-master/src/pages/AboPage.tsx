import React, {useEffect, useState} from 'react';
import {useParams} from 'react-router-dom';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {api} from '@/lib/api';
import {decodeTokenUnicode, copyToClipboard, formatErrorMessageWithMailto} from '@/lib/utils';
import { Toaster } from "@/components/ui/toaster";
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, DragEndEvent } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { toast } from "@/components/ui/use-toast";
import {format} from "date-fns";
import { Loader2 } from 'lucide-react';

// Interface pour les données d'abonnement
interface AboDetail {
  PCVID?: number;
  PCVNUM?: string;
  TIRID?: number;
  TIRCODE?: string;
  TIRSOCIETETYPE?: string;
  TIRSOCIETE?: string;
  F_TIRCODE?: string;
  F_TIRSOCIETETYPE?: string;
  F_TIRSOCIETE?: string;
  L_TIRCODE?: string;
  L_TIRSOCIETETYPE?: string;
  L_TIRSOCIETE?: string;
  ARTID?: number;
  ARTCODE?: string;
  ARTDESIGNATION?: string;
  ARTISABO?: string;
  ARTISCODESN?: string;
  PLVDIVERS?: string | null;
  PLVNUMSERIE?: string | null;
  QRCODE?: string;
  CODESN?: string;
  DATEDEBUT?: string;
  DATEFIN?: string;
  PASN_NUM?: string;
  ETAT_ABO?: string;
  OBJECT?: string;
  [key: string]: any;
}

interface AboCheckResponse {
  status: string;
  message: string;
  details: AboDetail[];
}

// Interface pour les données du formulaire TIR
interface TirFormData {
  TIRID: number;
  TIRCODE: string;
  TIRSOCIETETYPE: string;
  TIRSOCIETE: string;
}

// Interface pour les lignes du tableau
interface TableRow {
  id: string;
  type: 'article' | 'comment';
  content: any;
  commentPosition?: 'top' | 'bottom';
  commentIndex?: number;
}

interface ApiResponse<T> {
  success: boolean;
  data: T;
}

// Nouvelles interfaces pour les énumérations
interface EnumType {
  libelle: string;
}

interface EnumTypes {
  civilites: EnumType[];
  societeTypes: EnumType[];
}

// Interface pour les codes de transformation
interface PctCode {
  code: string;
  libelle: string;
}

// Composant DraggableRow
function DraggableRow({ id, children }: { id: string; children: React.ReactNode }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging
  } = useSortable({ id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <tr ref={setNodeRef} style={style}>
      <td className="w-8 px-2" {...attributes} {...listeners}>
        <div className="cursor-move">↕️</div>
      </td>
      {children}
    </tr>
  );
}

export default function AboPage() {
  const { token } = useParams<{ token: string }>();
  const queryClient = useQueryClient();
  const [tokenData, setTokenData] = useState<Record<string, any> | null>(null);
  const [selectedItems, setSelectedItems] = useState<AboDetail[]>([]);
  const [expandedItems, setExpandedItems] = useState<{[key: string]: boolean}>({});
  const [activeTab, setActiveTab] = useState<string>("facturation");
  const [showTabContent, setShowTabContent] = useState<boolean>(true);
  const [tirFormData, setTirFormData] = useState<{[key: string]: TirFormData}>({});
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [pendingSubmit, setPendingSubmit] = useState<{ itemKey: string, originalData: AboDetail } | null>(null);
  const [showCodeModal, setShowCodeModal] = useState(false);
  const [currentCodeSN, setCurrentCodeSN] = useState<string>('');
  const [expandedComments, setExpandedComments] = useState<{[key: string]: boolean}>({});
  const [comments, setComments] = useState<{[key: string]: string[]}>({});
  const [tableRows, setTableRows] = useState<TableRow[]>([]);
  const [showEditClientModal, setShowEditClientModal] = useState(false);
  const [newClientCode, setNewClientCode] = useState<string>('');
  const [editingClientType, setEditingClientType] = useState<'final' | 'facture' | 'livre'>('final');
  const [enumTypes, setEnumTypes] = useState<EnumTypes>({ civilites: [], societeTypes: [] });
  const [loadingCivilites, setLoadingCivilites] = useState<boolean>(false);
  const [loadingSocieteTypes, setLoadingSocieteTypes] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [selectedCivilite, setSelectedCivilite] = useState<string>('');
  const [selectedSocieteType, setSelectedSocieteType] = useState<string>('');
  const [clientValidation, setClientValidation] = useState<{
    isValid: boolean;
    message: string;
    details: any | null;
  }>({
    isValid: false,
    message: '',
    details: null
  });
  const [isCheckingClient, setIsCheckingClient] = useState(false);
  const [isConfigurationFinalized, setIsConfigurationFinalized] = useState(false);
  const [transformationCodes, setTransformationCodes] = useState<PctCode[]>([]);
  const [loadingTransformationCodes, setLoadingTransformationCodes] = useState<boolean>(false);
  const [selectedTransformation, setSelectedTransformation] = useState("ABOCLI->OFFRENOU");
  // Ajouter l'état pour le message d'erreur
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  // Ajouter l'état pour sauvegarder les lignes du tableau
  const [savedTableRows, setSavedTableRows] = useState<TableRow[]>([]);
  const [isRestoringFromSaved, setIsRestoringFromSaved] = useState(false);
  
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  // Décodage du token lors du chargement du composant
  useEffect(() => {
    if (token) {
      const decoded = decodeTokenUnicode(token);
      setTokenData(decoded);
    }
  }, [token]);

  // Initialiser les états des commentaires si nécessaire
  useEffect(() => {
    if (selectedItems.length > 0 && Object.keys(comments).length === 0) {
      const initialComments: {[key: string]: string[]} = {};
      const initialExpanded: {[key: string]: boolean} = {};
      
      selectedItems.forEach(item => {
        const itemKey = `${item.PCVID}-${item.ARTID}`;
        const topKey = `comment-${itemKey}-top`;
        const bottomKey = `comment-${itemKey}-bottom`;
        
        initialComments[topKey] = [];
        initialComments[bottomKey] = [];
        initialExpanded[topKey] = true;
        initialExpanded[bottomKey] = true;
      });
      
      setComments(initialComments);
      setExpandedComments(initialExpanded);
    }
  }, [selectedItems]);

  // Charger les données initiales
  const { data, isLoading } = useQuery<AboCheckResponse>({
    queryKey: ['aboCheck', tokenData?.user, tokenData?.pcvnum],
    queryFn: async () => {
      if (!tokenData?.user || !tokenData?.pcvnum) {
        throw new Error('Données du token manquantes');
      }
      const response = await api.get<AboCheckResponse>(
        `/api/abo/check?pcvnum=${tokenData.pcvnum}&user=${tokenData.user}`
      );
      return response;
    },
    enabled: !!tokenData?.user && !!tokenData?.pcvnum
  });

  // Nouvelle fonction pour charger les énumérations à la demande
  const loadEnumTypes = async (type: 1 | 4) => {
    try {
      if (type === 1 && enumTypes.civilites.length === 0 && !loadingCivilites) {
        setLoadingCivilites(true);
        const response = await api.get<ApiResponse<ApiResponse<EnumType[]>>>(`/api/abo/get-enum-types?type=1`);
        if (response.success && response.data.success) {
          setEnumTypes(prev => ({
            ...prev,
            civilites: response.data.data
          }));
        }
        setLoadingCivilites(false);
      } else if (type === 4 && enumTypes.societeTypes.length === 0 && !loadingSocieteTypes) {
        setLoadingSocieteTypes(true);
        const response = await api.get<ApiResponse<ApiResponse<EnumType[]>>>(`/api/abo/get-enum-types?type=4`);
        if (response.success && response.data.success) {
          setEnumTypes(prev => ({
            ...prev,
            societeTypes: response.data.data
          }));
        }
        setLoadingSocieteTypes(false);
      }
    } catch (error) {
      console.error('Erreur lors du chargement des énumérations:', error);
      if (type === 1) setLoadingCivilites(false);
      if (type === 4) setLoadingSocieteTypes(false);
    }
  };

  // Nouvelle fonction pour charger les codes de transformation
  const loadTransformationCodes = async () => {
    if (transformationCodes.length === 0 && !loadingTransformationCodes) {
      try {
        setLoadingTransformationCodes(true);
        const response = await api.get<{success: boolean, data: PctCode[]}>('/api/abo/get-pctcode');
        if (response.success) {
          setTransformationCodes(response.data);
        }
      } catch (error) {
        console.error('Erreur lors du chargement des codes de transformation:', error);
      } finally {
        setLoadingTransformationCodes(false);
      }
    }
  };

  // Charger les codes de transformation quand on passe en mode finalisé
  useEffect(() => {
    if (isConfigurationFinalized && transformationCodes.length === 0) {
      loadTransformationCodes();
    }
  }, [isConfigurationFinalized]);

  // Fonction pour générer les lignes du tableau
  useEffect(() => {
    if (!selectedItems.length) return;

    // Extraire les lignes de commentaires existantes
    const existingComments = tableRows.filter(row => row.type === 'comment');
    
    // Créer les lignes d'articles
    const articleRows = selectedItems.map(item => ({
      id: `article-${item.PCVID}-${item.ARTID}`,
      type: 'article' as 'article',
      content: item
    }));
    
    setTableRows(prevRows => {
      if (prevRows.length === 0) {
        return articleRows;
      }
      
      if (isRestoringFromSaved) {
        return prevRows;
      }
      
      return [...existingComments, ...articleRows];
    });
  }, [selectedItems]);

  // Gestion de la sélection d'un élément
  const handleSelectItem = (item: AboDetail) => {
    if (selectedItems.some(selected => 
      selected.PCVID === item.PCVID && 
      selected.ARTID === item.ARTID &&
      selected.PLVNUMSERIE === item.PLVNUMSERIE
    )) {
      setSelectedItems(selectedItems.filter(
        selected => !(
          selected.PCVID === item.PCVID && 
          selected.ARTID === item.ARTID &&
          selected.PLVNUMSERIE === item.PLVNUMSERIE
        )
      ));
      
      const itemKey = `${item.PCVID}-${item.ARTID}`;
      const newExpandedItems = {...expandedItems};
      delete newExpandedItems[itemKey];
      setExpandedItems(newExpandedItems);
      
      const newTirFormData = {...tirFormData};
      delete newTirFormData[itemKey];
      setTirFormData(newTirFormData);
    } else {
      setSelectedItems([...selectedItems, item]);
      
      const itemKey = `${item.PCVID}-${item.ARTID}`;
      setTirFormData({
        ...tirFormData,
        [itemKey]: {
          TIRID: item.TIRID || 0,
          TIRCODE: item.TIRCODE || '',
          TIRSOCIETETYPE: item.TIRSOCIETETYPE || '',
          TIRSOCIETE: item.TIRSOCIETE || ''
        }
      });
    }
  };

  // Vérifier si un élément est sélectionné
  const isItemSelected = (item: AboDetail) => {
    return selectedItems.some(selected => 
      selected.PCVID === item.PCVID && 
      selected.ARTID === item.ARTID &&
      selected.PLVNUMSERIE === item.PLVNUMSERIE
    );
  };

  const handleInputChange = (itemKey: string, e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setTirFormData({
      ...tirFormData,
      [itemKey]: {
        ...tirFormData[itemKey],
        [name]: value
      }
    });
    
    setSelectedItems(prevItems => 
      prevItems.map(item => {
        if (`${item.PCVID}-${item.ARTID}` === itemKey) {
          return {
            ...item,
            [name]: value
          };
        }
        return item;
      })
    );
  };
  const prepareSubmit = (itemKey: string, e: React.FormEvent) => {
    e.preventDefault();
    
    setSavedTableRows(JSON.parse(JSON.stringify(tableRows)));
    
    setIsConfigurationFinalized(true);
    setShowTabContent(false);
    
    toast({
      title: "Succès",
      description: "La configuration a été finalisée avec succès",
      variant: "success",
    });
  };

  const handleCancelFinalization = () => {
    
    setIsConfigurationFinalized(false);
    setShowTabContent(true);
    
    setIsRestoringFromSaved(true);
    
    if (savedTableRows.length > 0) {
      const deepCopiedRows = JSON.parse(JSON.stringify(savedTableRows));
      setTableRows(deepCopiedRows);
    }
    const currentItems = [...selectedItems];
    setSelectedItems([]);
    setTimeout(() => {
      setSelectedItems(currentItems);   
    }, 50);
    
    toast({
      title: "Configuration restaurée",
      description: "Vous pouvez continuer à modifier la configuration",
      variant: "warning",
    });
  };

  // Formater une date en format français
  const formatDateFr = (dateStr: string | undefined | null): string => {
    if (!dateStr) return '';
    try {
      const date = new Date(dateStr);
      return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      }).format(date);
    } catch (error) {
      return dateStr;
    }
  };
  
  // Afficher le modal pour le code S/N
  const showFullCodeSN = (codeSN: string, e: React.MouseEvent) => {
    e.stopPropagation();
    if (codeSN) {
      setCurrentCodeSN(codeSN);
      setShowCodeModal(true);
    }
  };
  
  // Fermer le modal du code S/N
  const closeCodeModal = () => {
    setShowCodeModal(false);
    setCurrentCodeSN('');
  };

  // Ajouter un commentaire
  const addComment = (afterId: string) => {
    const commentId = `comment-${Date.now()}`;
    
    // Créer une nouvelle ligne pour l'interface
    const newRow = {
      id: commentId,
      type: 'comment' as 'comment',
      content: ''
    };
    
    // Mise à jour de tableRows
    setTableRows(prevRows => {
      // Pour le bouton du haut, insérer au début
      if (afterId === 'top') {
        return [newRow, ...prevRows];
      }
      
      // Pour les autres boutons, insérer après l'élément cliqué
      const afterIndex = prevRows.findIndex(row => row.id === afterId);
      if (afterIndex === -1) {
        return [...prevRows, newRow]; // Fallback
      }
      
      const newRows = [...prevRows];
      newRows.splice(afterIndex + 1, 0, newRow);
      return newRows;
    });
    
    // Mettre à jour l'état des commentaires
    setComments(prev => ({
      ...prev,
      [commentId]: ['']
    }));
  };
  
  // Supprimer une ligne de commentaire
  const removeCommentLine = (commentId: string) => {
    // Supprimer de tableRows
    setTableRows(prevRows => prevRows.filter(row => row.id !== commentId));
    
    // Supprimer de l'état des commentaires
    setComments(prev => {
      const newComments = { ...prev };
      delete newComments[commentId];
      return newComments;
    });
  };
  
  // Gérer les changements de commentaires
  const handleCommentChange = (commentId: string, content: string) => {
    // Mettre à jour tableRows pour l'affichage immédiat
    setTableRows(prevRows => 
      prevRows.map(row => 
        row.id === commentId 
          ? { ...row, content: content } 
          : row
      )
    );
    
    // Mettre à jour l'état des commentaires pour la persistance
    setComments(prev => ({
      ...prev,
      [commentId]: [content]
    }));
  };

  // Gestion du drag and drop
  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    
    if (over && active.id !== over.id) {
      setTableRows((rows) => {
        const oldIndex = rows.findIndex((row) => row.id === active.id);
        const newIndex = rows.findIndex((row) => row.id === over.id);
        const newRows = arrayMove(rows, oldIndex, newIndex);

        const articlesOnly = newRows.filter(row => row.type === 'article').map(row => row.content);
        if (JSON.stringify(selectedItems) !== JSON.stringify(articlesOnly)) {
          setSelectedItems(articlesOnly);
        }
        
        return newRows;
      });
    }
  };

  // Modifier la fonction handleEditClientCode
  const handleEditClientCode = (type: 'final' | 'facture' | 'livre') => {
    setEditingClientType(type);
    setNewClientCode('');
    setClientValidation({
      isValid: false,
      message: '',
      details: null
    });
    setShowEditClientModal(true);
  };

  // Modifier la fonction qui gère le changement de code client
  const handleClientCodeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const code = e.target.value;
    setNewClientCode(code);
    if (code && code.trim() !== '') {
      checkClientCode(code);
    } else {
      setClientValidation({
        isValid: false,
        message: '',
        details: null
      });
    }
  };

  // Vérification du code client
  const checkClientCode = async (code: string) => {
    if (!code || code.trim() === '') {
      setClientValidation({
        isValid: false,
        message: '',
        details: null
      });
      return;
    }

    setIsCheckingClient(true);
    try {
      const response = await api.get<{success: boolean, details: any[]}>(`/api/abo/check-code-client?user=${tokenData?.user}&codeClient=${code}&pcvnum=${tokenData?.pcvnum}&type=${editingClientType}`);
      
      if (response.success && response.details && response.details.length > 0) {
        const details = response.details[0];
        const societeType = editingClientType === 'final' 
          ? details.TIRSOCIETETYPE 
          : editingClientType === 'facture' 
          ? details.F_TIRSOCIETETYPE 
          : details.L_TIRSOCIETETYPE;
        
        const societe = editingClientType === 'final' 
          ? details.TIRSOCIETE 
          : editingClientType === 'facture' 
          ? details.F_TIRSOCIETE 
          : details.L_TIRSOCIETE;

        setClientValidation({
          isValid: true,
          message: `${societeType} ${societe}`,
          details: details
        });
      } else {
        setClientValidation({
          isValid: false,
          message: 'Code client erroné',
          details: null
        });
      }
    } catch (error) {
      setClientValidation({
        isValid: false,
        message: 'Erreur lors de la vérification du code client',
        details: null
      });
    } finally {
      setIsCheckingClient(false);
    }
  };

  // Enregistrer le code client
  const handleSaveClientCode = async () => {
    if (!clientValidation.isValid || !clientValidation.details) return;
    
    const newDetails = clientValidation.details;

    if (editingClientType === 'final') {
      const updatedItems = selectedItems.map(item => ({
        ...item,
        // Client final
        TIRID: newDetails.TIRID,
        TIRCODE: newDetails.TIRCODE,
        TIRSOCIETETYPE: newDetails.TIRSOCIETETYPE,
        TIRSOCIETE: newDetails.TIRSOCIETE,
        // Client facturé
        F_TIRID: newDetails.TIRID,
        F_TIRCODE: newDetails.F_TIRCODE || newDetails.TIRCODE,
        F_TIRSOCIETETYPE: newDetails.F_TIRSOCIETETYPE || newDetails.TIRSOCIETETYPE,
        F_TIRSOCIETE: newDetails.F_TIRSOCIETE || newDetails.TIRSOCIETE,
        F_CONTACT_TYPE: newDetails.F_CONTACT_TYPE || newDetails.CONTACT_TYPE,
        F_CONTACT_NOM: newDetails.F_CONTACT_NOM || newDetails.CONTACT_NOM,
        F_CONTACT_PRENOM: newDetails.F_CONTACT_PRENOM || newDetails.CONTACT_PRENOM,
        F_ADRL1: newDetails.F_ADRL1 || newDetails.ADRL1,
        F_ADRL2: newDetails.F_ADRL2 || newDetails.ADRL2,
        F_ADRL3: newDetails.F_ADRL3 || newDetails.ADRL3,
        F_ADRCODEPOSTAL: newDetails.F_ADRCODEPOSTAL || newDetails.ADRCODEPOSTAL,
        F_ADRVILLE: newDetails.F_ADRVILLE || newDetails.ADRVILLE,
        F_ADRPAYS: newDetails.F_ADRPAYS || newDetails.ADRPAYS,
        F_ADRTEL: newDetails.F_ADRTEL || newDetails.ADRTEL,
        F_ADRPORTABLE: newDetails.F_ADRPORTABLE || newDetails.ADRPORTABLE,
        F_ADRMAIL: newDetails.F_ADRMAIL || newDetails.ADRMAIL,
        // Client livré
        L_TIRID: newDetails.TIRID,
        L_TIRCODE: newDetails.L_TIRCODE || newDetails.TIRCODE,
        L_TIRSOCIETETYPE: newDetails.L_TIRSOCIETETYPE || newDetails.TIRSOCIETETYPE,
        L_TIRSOCIETE: newDetails.L_TIRSOCIETE || newDetails.TIRSOCIETE,
        L_CONTACT_TYPE: newDetails.L_CONTACT_TYPE || newDetails.CONTACT_TYPE,
        L_CONTACT_NOM: newDetails.L_CONTACT_NOM || newDetails.CONTACT_NOM,
        L_CONTACT_PRENOM: newDetails.L_CONTACT_PRENOM || newDetails.CONTACT_PRENOM,
        L_ADRL1: newDetails.L_ADRL1 || newDetails.ADRL1,
        L_ADRL2: newDetails.L_ADRL2 || newDetails.ADRL2,
        L_ADRL3: newDetails.L_ADRL3 || newDetails.ADRL3,
        L_ADRCODEPOSTAL: newDetails.L_ADRCODEPOSTAL || newDetails.ADRCODEPOSTAL,
        L_ADRVILLE: newDetails.L_ADRVILLE || newDetails.ADRVILLE,
        L_ADRPAYS: newDetails.L_ADRPAYS || newDetails.ADRPAYS,
        L_ADRTEL: newDetails.L_ADRTEL || newDetails.ADRTEL,
        L_ADRPORTABLE: newDetails.L_ADRPORTABLE || newDetails.ADRPORTABLE,
        L_ADRMAIL: newDetails.L_ADRMAIL || newDetails.ADRMAIL
      }));
      setSelectedItems(updatedItems);
    } else if (editingClientType === 'facture') {
      const updatedItems = selectedItems.map(item => ({
        ...item,
        F_TIRID: newDetails.TIRID,
        F_TIRCODE: newDetails.F_TIRCODE,
        F_TIRSOCIETETYPE: newDetails.F_TIRSOCIETETYPE,
        F_TIRSOCIETE: newDetails.F_TIRSOCIETE,
        F_CONTACT_TYPE: newDetails.F_CONTACT_TYPE,
        F_CONTACT_NOM: newDetails.F_CONTACT_NOM,
        F_CONTACT_PRENOM: newDetails.F_CONTACT_PRENOM,
        F_ADRL1: newDetails.F_ADRL1,
        F_ADRL2: newDetails.F_ADRL2,
        F_ADRL3: newDetails.F_ADRL3,
        F_ADRCODEPOSTAL: newDetails.F_ADRCODEPOSTAL,
        F_ADRVILLE: newDetails.F_ADRVILLE,
        F_ADRPAYS: newDetails.F_ADRPAYS,
        F_ADRTEL: newDetails.F_ADRTEL,
        F_ADRPORTABLE: newDetails.F_ADRPORTABLE,
        F_ADRMAIL: newDetails.F_ADRMAIL
      }));
      setSelectedItems(updatedItems);
    } else {
      const updatedItems = selectedItems.map(item => ({
        ...item,
        L_TIRID: newDetails.TIRID,
        L_TIRCODE: newDetails.L_TIRCODE,
        L_TIRSOCIETETYPE: newDetails.L_TIRSOCIETETYPE,
        L_TIRSOCIETE: newDetails.L_TIRSOCIETE,
        L_CONTACT_TYPE: newDetails.L_CONTACT_TYPE,
        L_CONTACT_NOM: newDetails.L_CONTACT_NOM,
        L_CONTACT_PRENOM: newDetails.L_CONTACT_PRENOM,
        L_ADRL1: newDetails.L_ADRL1,
        L_ADRL2: newDetails.L_ADRL2,
        L_ADRL3: newDetails.L_ADRL3,
        L_ADRCODEPOSTAL: newDetails.L_ADRCODEPOSTAL,
        L_ADRVILLE: newDetails.L_ADRVILLE,
        L_ADRPAYS: newDetails.L_ADRPAYS,
        L_ADRTEL: newDetails.L_ADRTEL,
        L_ADRPORTABLE: newDetails.L_ADRPORTABLE,
        L_ADRMAIL: newDetails.L_ADRMAIL
      }));
      setSelectedItems(updatedItems);
    }

    setShowEditClientModal(false);
    toast({
      title: "Succès",
      description: "Les informations du client ont été mises à jour avec succès",
      variant: "success",
    });
  };

  // Fonction pour finaliser l'abonnement
  const handleFinalizeSubscription = async () => {
    try {
      setIsSubmitting(true);
      // Récupérer les références aux éléments du formulaire d'abonnement
      const dateDebutEl = document.querySelector('input[name="dateDebut"]') as HTMLInputElement;
      const isActiveEl = document.querySelector('input[name="isActive"]') as HTMLInputElement;
      const nombreEcheancesEl = document.querySelector('input[name="nombreEcheances"]') as HTMLInputElement;
      const periodiciteEl = document.querySelector('input[name="periodicite"]') as HTMLInputElement;
      const periodiciteTypeEl = document.querySelector('select[name="periodiciteType"]') as HTMLSelectElement;
      const derniereEcheanceEl = document.querySelector('input[name="derniereEcheance"]') as HTMLInputElement;
      const prochaineEcheanceEl = document.querySelector('input[name="prochaineEcheance"]') as HTMLInputElement;
      const alerteJoursEl = document.querySelector('input[name="alerteJours"]') as HTMLInputElement;
      const renouvellementEl = document.querySelector('select[name="renouvellement"]') as HTMLSelectElement;
      const modeRevisionEl = document.querySelector('select[name="modeRevision"]') as HTMLSelectElement;
      const dateResiliationEl = document.querySelector('input[name="dateResiliation"]') as HTMLInputElement;
      const transformationEl = document.querySelector('select[name="transformation"]') as HTMLSelectElement;

      const automateE = {
        E1: 'E',
        E2: 'ABOCLI',
        E3: format(selectedItems[0]?.PCVDATEEFFET, 'dd/MM/yyyy'),
        E4: selectedItems[0]?.TIRCODE,
        E5: selectedItems[0]?.PCVISHT,
        E6: 'ABOA',
        E7: '',
        E8: selectedItems[0]?.PCVNUMEXT,
        E9: selectedItems[0]?.F_TIRCODE,
        E10: selectedItems[0]?.TRFCODE,
        E11: selectedItems[0]?.CODECOM,
        E12: selectedItems[0]?.AFFID,
        E13: selectedItems[0]?.DEVSYMBOLE,
        E14: '',
        E15: selectedItems[0]?.L_TIRCODE,
        E16: selectedItems[0]?.DEPCODE,
        E17: '',
        E18: '',
        E19: '',
        E20: selectedItems[0]?.CODEETAB,
        E21: '',
        E22: '',
        E23: '',
        E24: selectedItems[0]?.TYNCODE,
        E25: format(selectedItems[0]?.PCVDATELIVRAISON, 'dd/MM/yyyy'),
        E26: selectedItems[0]?.OBJECT,
        E27: '',
        E28: selectedItems[0]?.TIRCODE,
        E29: '',
        E30: '',
        E31: '',
        E32: '',
        E33: '',
        E34: '',
        E35: '',
        E36: '',
        E37: '',
        E38: '',
      };

      const automateAB = {
        AB1: 'AB',
        AB2: format(dateDebutEl.value, 'dd/MM/yyyy'),
        AB3: isActiveEl.checked ? 'O' : 'N',
        AB4: transformationEl.value,
        AB5: nombreEcheancesEl.value,
        AB6: periodiciteEl.value,
        AB7: periodiciteTypeEl.value,
        AB8: format(derniereEcheanceEl.value, 'dd/MM/yyyy'),
        AB9: format(prochaineEcheanceEl.value, 'dd/MM/yyyy'),
        AB10: alerteJoursEl.value,
        AB11: renouvellementEl.value,
        AB12: modeRevisionEl.value,
        AB13: format(dateResiliationEl.value, 'dd/MM/yyyy'),
      };

      const automateAF = {
        AF1: 'AF',
        AF2: selectedItems[0]?.F_TIRSOCIETETYPE,
        AF3: selectedItems[0]?.F_TIRSOCIETE,
        AF4: selectedItems[0]?.F_CONTACT_TYPE,
        AF5: selectedItems[0]?.F_CONTACT_NOM,
        AF6: selectedItems[0]?.F_ADRL1,
        AF7: selectedItems[0]?.F_ADRL2,
        AF8: selectedItems[0]?.F_ADRL3,
        AF9: selectedItems[0]?.F_ADRCODEPOSTAL,
        AF10: selectedItems[0]?.F_ADRVILLE,
        AF11: selectedItems[0]?.F_ADRPAYS,
        AF12: selectedItems[0]?.F_ADRMAIL,
        AF13: selectedItems[0]?.F_ADRTEL,
        AF14: '',
        AF15: selectedItems[0]?.F_ADRSIRET,
        AF16: selectedItems[0]?.F_ADRAPE,
        AF17: selectedItems[0]?.F_ADRNUMTVA,
        AF18: selectedItems[0]?.F_ADRRCS,
        AF19: selectedItems[0]?.F_CONTACT_PRENOM,
        AF20: '',
        AF21: '',
        AF22: selectedItems[0]?.F_ADRPORTABLE,
        AF23: '',
      };

      const automateAL = {
        AL1: 'AL',
        AL2: selectedItems[0]?.L_TIRSOCIETETYPE,
        AL3: selectedItems[0]?.L_TIRSOCIETE,
        AL4: selectedItems[0]?.L_CONTACT_TYPE,
        AL5: selectedItems[0]?.L_CONTACT_NOM,
        AL6: selectedItems[0]?.L_ADRL1,
        AL7: selectedItems[0]?.L_ADRL2,
        AL8: selectedItems[0]?.L_ADRL3,
        AL9: selectedItems[0]?.L_ADRCODEPOSTAL,
        AL10: selectedItems[0]?.L_ADRVILLE,
        AL11: selectedItems[0]?.L_ADRPAYS,
        AL12: selectedItems[0]?.L_ADRMAIL,
        AL13: selectedItems[0]?.L_ADRTEL,
        AL14: '',
        AL15: '',
        AL16: '',
        AL17: '',
        AL18: '',
        AL19: '',
        AL20: selectedItems[0]?.L_CONTACT_PRENOM,
        AL21: selectedItems[0]?.F_ADRPORTABLE,
        AL22: '',
      };

      const automateAA = {
        AA1: 'AA',
        AA2: selectedItems[0]?.TIRSOCIETETYPE,
        AA3: selectedItems[0]?.TIRSOCIETE,
        AA4: selectedItems[0]?.CF_CONTACT_TYPE,
        AA5: selectedItems[0]?.CF_CONTACT_NOM,
        AA6: selectedItems[0]?.CF_ADRL1,
        AA7: selectedItems[0]?.CF_ADRL2,
        AA8: selectedItems[0]?.CF_ADRL3,
        AA9: selectedItems[0]?.CF_ADRCODEPOSTAL,
        AA10: selectedItems[0]?.CF_ADRVILLE,
        AA11: selectedItems[0]?.CF_ADRPAYS,
        AA12: selectedItems[0]?.CF_ADRMAIL,
        AA13: selectedItems[0]?.CF_ADRTEL,
        AA14: '',
        AA15: selectedItems[0]?.CF_ADRSIRET,
        AA16: selectedItems[0]?.CF_ADRAPE,
        AA17: selectedItems[0]?.CF_ADRNUMTVA,
        AA18: selectedItems[0]?.CF_ADRRCS,
        AA19: selectedItems[0]?.CF_CONTACT_PRENOM,
        AA20: selectedItems[0]?.CF_ADRSERVICE,
        AA21: selectedItems[0]?.CF_ADRDEPARTEMENT,
        AA22: selectedItems[0]?.CF_ADRPORTABLE,
        AA23: '',
      };

      const automateAE = {
        AE1: 'AE',
        AE2: selectedItems[0]?.TIRSOCIETETYPE,
        AE3: selectedItems[0]?.TIRSOCIETE,
        AE4: selectedItems[0]?.CF_CONTACT_TYPE,
        AE5: selectedItems[0]?.CF_CONTACT_NOM,
        AE6: selectedItems[0]?.CF_ADRL1,
        AE7: selectedItems[0]?.CF_ADRL2,
        AE8: selectedItems[0]?.CF_ADRL3,
        AE9: selectedItems[0]?.CF_ADRCODEPOSTAL,
        AE10: selectedItems[0]?.CF_ADRVILLE,
        AE11: selectedItems[0]?.CF_ADRPAYS,
        AE12: selectedItems[0]?.CF_ADRMAIL,
        AE13: selectedItems[0]?.CF_ADRTEL,
        AE14: '',
        AE15: '',
        AE16: '',
        AE17: '',
        AE18: '',
        AE19: '',
        AE20: selectedItems[0]?.CF_CONTACT_PRENOM,
        AE21: selectedItems[0]?.CF_ADRPORTABLE,
        AE22: '',
      };
      
      const findOriginalItem = (rowId: string): AboDetail | null => {
        if (!data || !data.details) return null;
        
        const idParts = rowId.split('-');
        if (idParts.length === 2) {
          const pcvId = parseInt(idParts[0], 10);
          const artId = parseInt(idParts[1], 10);
          
          return data.details.find(item =>
            item.PCVID === pcvId && item.ARTID === artId
          ) || null;
        }
        
        return null;
      };
      
      let laCounter = 1;
      let ldCounter = 1;
      let anCounter = 1;
      
      const assignedLA: {[key: string]: string} = {};
      const assignedLD: {[key: string]: string} = {};
      const assignedAN: {[key: string]: string} = {};

      
      // ====== TABLEAU PRINCIPAL (LIGNES) AVEC IDENTIFIANTS ET DONNÉES COMPLÈTES ======
      const tableauPrincipal = tableRows.map((row, index) => {
        const lineNumber = index + 1;
        
        if (row.type === 'comment') {
          const anKey = `LC${anCounter}`;
          assignedAN[anKey] = `L${lineNumber}`;
          anCounter++;
          
          const automateLC: {[key: string]: string} = {
            LC1: 'LC',
            LC2: row.content,
            LC3: '',
            LC4: 'O',
            LC5: 'O',
            LC6: 'N',
            LC7: 'N',
            LC8: 'N',
            LC9: 'N',
            LC10: 'O',
            LC11: '8',
          };
          
          return {
            TYPE: 'comment',
            LIGNE_ID: `L${lineNumber}`,
            automateLC: automateLC
          };
        } else {
          const currentItem = row.content;
          const originalItem = findOriginalItem(row.id);
          const item = originalItem || currentItem;
          const laKey = `LA${laCounter}`;
          assignedLA[laKey] = `L${lineNumber}`;
          laCounter++;

          const automateLA: {[key: string]: string} = {
            LA1: 'LA',
            LA2: item.ARTCODE,
            LA3: item.PLVQTE,
            LA4: item.PLVISIMPRIMABLE,
            LA5: item.PLVDESIGNATION,
            LA6: item.PLVPUBRUT,
            LA7: item.PLVREMISE_MNT,
            LA8: item.PLVPUNET,
            LA9: format(item.PLVDATE, 'dd/MM/yyyy'),
            LA10: item.PLVDIVERS,
            LA11: item.PLVNUMLOT,
            LA12: item.PLVNUMSERIE,
            LA13: item.TVACODE,
            LA14: item.TPFCODE,
            LA15: item.CPTCODE,
            LA16: item.DEPCODE,
            LA17: item.CODECOM,
            LA18: item.AFFCODE,
            LA19: item.TRFCODE,
            LA20: item.PLVSTYLEISGRAS,
            LA21: item.PLVSTYLEISITALIC,
            LA22: item.PLVSTYLEISIMPPARTIEL,
            LA23: item.PLVSTYLEISSOULIGNE,
            LA24: item.PLVLASTPA,
            LA25: item.TPFCODE1,
            LA26: item.TPFCODE2,
            LA27: item.TPFCODE3,
            LA28: item.TPFCODE4,
            LA29: item.TPFCODE5,
            LA30: item.TPFCODE6,
            LA31: item.TPFCODE7,
            LA32: item.TPFCODE8,
            LA33: item.TPFCODE9,
            LA34: item.PLVD1,
            LA35: item.PLVD2,
            LA36: item.PLVD3,
            LA37: item.PLVD4,
            LA38: item.PLVD5,
            LA39: item.PLVD6,
            LA40: item.PLVD7,
            LA41: item.PLVD8,
            LA42: item.PLVFEFOPEREMPTION,
            LA43: item.ANSCODE,
            LA44: item.PLVQTETRANSFO,
            LA45: '',
            LA46: '',
            LA47: '',
            LA48: '',
            LA49: '',
            LA50: item.PLVSTYLEISBARRE,
            LA51: item.PLVSTYLECOULEUR,
            LA52: item.PLVSTYLETAILLE
          };
          const automateLD: {[key: string]: string} = {
            LD1: 'LD',
            LD2: 'QRCODE',
            LD3: item.QRCODE,
            LD4: 'CODESN',
            LD5: item.CODESN,
            LD6: 'DATEDEBUT',
            LD7: format(item.DATEDEBUT, 'dd/MM/yyyy'),
            LD8: 'DATEFIN',
            LD9: format(item.DATEFIN, 'dd/MM/yyyy')
          };
          
          let ldKey = null;
          if (item.PLVDIVERS || item.PLVNUMSERIE) {
            ldKey = `LD${ldCounter}`;
            assignedLD[ldKey] = `L${lineNumber}`;
            ldCounter++;
          }
          
          return {
            TYPE: 'article',
            LIGNE_ID: `L${lineNumber}`,
            automateLA: automateLA,
            automateLD: automateLD
          };
        }
      });
      const memoId = selectedItems[0]?.MEMOID;
      const dataToSend = {
        pcvnum: tokenData?.pcvnum,
        user: tokenData?.user,
        tableauPrincipal,
        automateE,
        automateAB,
        automateAF,
        automateAL,
        automateAA,
        automateAE,
        memoId
      };
      
      try {
        const response = await api.post('/api/abo/create', dataToSend);
        
        if (response.success) {
          setErrorMessage(null);
          
          // Afficher un message de succès
          toast({
            title: "Abonnement validé",
            description: "L'abonnement a été validé avec succès",
            variant: "success",
          });
          
          setIsConfigurationFinalized(false);
          setShowTabContent(true);
          
          // Réinitialiser les états
          setSelectedItems([]);
          setTableRows([]);
          setSavedTableRows([]);
          
          // Invalider et recharger les données
          await queryClient.invalidateQueries({
            queryKey: ['aboCheck', tokenData?.user, tokenData?.pcvnum]
          });
          
          toast({
            title: "Données actualisées",
            description: "Les lignes ont été mises à jour avec le statut d'abonnement",
            variant: "info",
          });
        } else {
          const message = response.details?.message || response.message;
          setErrorMessage(message);
        }
      } catch (error: any) {
        const message = error.response?.data?.details?.message || error.response?.data?.message || 
                        "Une erreur est survenue lors de la communication avec le serveur";
        setErrorMessage(message);
      } finally {
        setIsSubmitting(false);
      }
    } catch (error: any) {
      setErrorMessage("Une erreur est survenue lors de la préparation des données d'abonnement");
      setIsSubmitting(false);
    }
  };

  const getOverlayStyle = () => {
    if (!isConfigurationFinalized) return {};
    
    return {
      position: 'relative' as const,
      pointerEvents: 'auto' as const,
    };
  };

  const renderOverlay = () => {
    if (!isConfigurationFinalized) return null;
    
    return (
      <div 
        style={{
          position: 'absolute' as const,
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          backgroundColor: 'rgba(255, 255, 255, 0.4)',
          backgroundImage: 'radial-gradient(circle, #e0e0e0 1px, transparent 2px)',
          backgroundSize: '20px 20px',
          pointerEvents: 'all' as const,
          zIndex: 20,
          borderRadius: '0.5rem',
          outline: 'none'
        }}
        aria-hidden="true"
        onKeyDown={(e) => {
          e.stopPropagation();
          e.preventDefault();
        }}
        tabIndex={-1}
      />
    );
  };

  const addKeyboardDisabledProps = () => {
    if (!isConfigurationFinalized) return {};
    
    return {
      tabIndex: -1,
      'aria-hidden': true,
      inert: 'inert',
      onKeyDown: (e: React.KeyboardEvent) => {
        e.stopPropagation();
        e.preventDefault();
        return false;
      }
    };
  };
  
  const toggleContent = () => {
    setShowTabContent(prevState => !prevState);
  };

  const calculateSubscriptionDates = () => {
    if (!selectedItems || selectedItems.length === 0) return {
      startDate: '',
      endDate: '',
      lastPaymentDate: '',
      nextPaymentDate: '',
      terminationDate: '',
      days: 0
    };

    const startDates = selectedItems
      .map(item => item.DATEDEBUT ? new Date(item.DATEDEBUT) : null)
      .filter(Boolean) as Date[];
    
    const endDatesWithInfo = selectedItems
      .filter(item => item.DATEFIN)
      .map(item => ({
        date: new Date(item.DATEFIN as string),
        serialNumber: item.PLVNUMSERIE || 'N/A',
        articleCode: item.ARTCODE || 'N/A'
      }));
    
    const endDates = endDatesWithInfo.map(item => item.date);
    
    console.log('Dates de fin avec numéros de série:', endDatesWithInfo);
    
    if (startDates.length === 0 || endDates.length === 0) return {
      startDate: '',
      endDate: '',
      lastPaymentDate: '',
      nextPaymentDate: '',
      terminationDate: '',
      days: 0
    };

    const startDate = new Date(Math.min(...startDates.map(date => date.getTime())));


    const endDate = new Date(Math.max(...endDates.map(date => date.getTime())));
    
    const diffTime = Math.abs(endDate.getTime() - startDate.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    const formatDateForInput = (date: Date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };
    return {
      startDate: formatDateForInput(startDate),
      endDate: formatDateForInput(endDate),
      lastPaymentDate: formatDateForInput(startDate),
      nextPaymentDate: formatDateForInput(endDate),
      terminationDate: formatDateForInput(endDate),
      days: diffDays
    };
  };

  return (
    <div className="min-h-screen bg-gray-100 p-6">
      <div className="mx-auto">
        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <h1 className="text-2xl font-semibold text-gray-800 mb-4">
            Détails du {tokenData?.pcvnum || ''}
          </h1>

          {data && data.status === 'success' ? (
            <div className="space-y-6">
              {/* Tableau principal avec les infos minimalistes */}
              <div className="overflow-x-auto relative" style={isConfigurationFinalized ? getOverlayStyle() : {}}>
                {isConfigurationFinalized && renderOverlay()}
                <table className="min-w-full bg-white border border-gray-200" {...(isConfigurationFinalized ? addKeyboardDisabledProps() : {})}>
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Code article</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro de série</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Divers ligne</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date début</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date fin</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Num abo</th>
                       <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Code S/N</th>
                          <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">QR</th>
                      <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Sélection</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {data.details.map((item, index) => (
                      <tr 
                        key={`item-${item.PCVID}-${item.ARTID}-${index}`}
                        className={`${isItemSelected(item) ? "bg-green-50" : "hover:bg-gray-50"} ${
                          item.ETAT_ABO === "HIGH" ? "bg-gray-100" : "cursor-pointer"
                        }`}
                        onClick={() => item.ETAT_ABO !== "HIGH" && handleSelectItem(item)}
                        style={{
                          opacity: item.ETAT_ABO === "HIGH" ? 0.4 : 1
                        }}
                        title={item.ETAT_ABO === "HIGH" ? "Cette ligne n'est pas sélectionnable car un abonnement est déjà en cours" : ""}
                      >
                        <td className="py-2 px-4 text-sm text-left">{item.ARTCODE}</td>
                        <td className="py-2 px-4 text-sm text-left">{item.ARTDESIGNATION}</td>
                        <td className="py-2 px-4 text-sm text-left">{item.PLVNUMSERIE}</td>
                        <td className="py-2 px-4 text-sm text-left">{item.PLVDIVERS}</td>
                        <td className="py-2 px-4 text-sm text-center">{formatDateFr(item.DATEDEBUT)}</td>
                        <td className="py-2 px-4 text-sm text-center">{formatDateFr(item.DATEFIN)}</td>
                        <td className="py-2 px-4 text-sm text-left">{item.PASN_NUM}</td>
                        <td className="py-2 px-4 text-sm text-left">
                          {item.CODESN ? (
                            <button 
                              onClick={(e) => showFullCodeSN(item.CODESN || '', e)}
                              className="max-w-[150px] truncate text-blue-600 hover:underline text-center"
                              title="Cliquez pour voir le code complet"
                            >
                              {item.CODESN}
                            </button>
                          ) : (
                            '-'
                          )}
                        </td>
                        <td className="py-2 px-4 text-sm">{item.QRCODE}</td>
                        <td className="py-2 px-4 text-sm" onClick={(e) => e.stopPropagation()}>
                          <input 
                            type="checkbox" 
                            checked={isItemSelected(item)}
                            onChange={() => item.ETAT_ABO !== "HIGH" && handleSelectItem(item)}
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            disabled={item.ETAT_ABO === "HIGH"}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {data.details.some(item => item.ETAT_ABO === "HIGH") && (
                  <div className="mt-2 flex items-center text-amber-600 bg-amber-50 p-2 rounded-md">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    <span className="text-sm font-medium">
                      Certaines lignes ne sont pas sélectionnables car des abonnements sont déjà en cours.
                    </span>
                  </div>
                )}
              </div>

              {/* Tableau détaillé pour les éléments sélectionnés */}
              {selectedItems.length > 0 && (
                <div className="mt-8">
                  <h2 className="text-xl font-semibold text-gray-800 mb-4">Détails des éléments sélectionnés</h2>

                  {/* Filtre global pour désactiver les interactions quand la configuration est finalisée */}
                  <div className="relative">
                    {isConfigurationFinalized && <div style={getOverlayStyle()}></div>}

                    {/* Informations du client final */}
                    <div className="bg-gray-50 p-2 rounded-lg mb-6" style={getOverlayStyle()}>
                      {renderOverlay()}
                      <div className="flex items-center gap-6" aria-hidden={isConfigurationFinalized} tabIndex={-1}>
                        <div className="flex items-center gap-2">
                          <label className="text-sm font-medium text-gray-700">Code client final</label>
                          <div className="flex items-center gap-2">
                            <input
                              type="text"
                              value={selectedItems[0]?.TIRCODE || ''}
                              className="w-24 px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                              readOnly
                            />
                            <button
                              type="button"
                              onClick={() => handleEditClientCode('final')}
                              className="p-1 text-gray-500 hover:text-gray-700"
                              title="Modifier le code client"
                              {...addKeyboardDisabledProps()}
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                              </svg>
                            </button>
                          </div>
                        </div>
                        <div className="flex items-center gap-2 flex-1">
                          <label className="text-sm font-medium text-gray-700">Société</label>
                          <div className="flex flex-1">
                            <input
                              type="text"
                              value={selectedItems[0]?.TIRSOCIETETYPE || ''}
                              className="w-14 px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                              readOnly
                            />
                            <input
                              type="text"
                              value={selectedItems[0]?.TIRSOCIETE || ''}
                              className="px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                              readOnly
                            />
                          </div>
                        </div>
                      </div>
                       <div className="flex items-center gap-2 flex-1 mt-5">
                        <label className="text-sm font-medium text-gray-700">Objet</label>
                        <input
                                type="text"
                                value={selectedItems[0]?.OBJECT || ''}
                                onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                name="OBJECT"
                                className="w-96 px-2 py-1 border border-gray-300 rounded-md text-sm"
                                {...addKeyboardDisabledProps()}
                              />
                       </div>
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                      <div>
                        <div className="flex items-center mb-1">
                          <h3 className="text-lg font-medium text-gray-800 flex items-center">
                            Facturation
                            <span className={`ml-2 text-sm font-normal ${
                              selectedItems[0]?.F_TIRCODE && selectedItems[0]?.F_TIRCODE !== selectedItems[0]?.TIRCODE
                                ? 'text-orange-500'
                                : 'text-gray-600'
                            }`}>
                              (Code client facturé : {selectedItems[0]?.F_TIRCODE || 'Non défini'})
                            </span>
                          </h3>
                        </div>
                        <div className="flex items-center gap-2 mb-2">
                          <button
                            onClick={toggleContent}
                            className="w-5 h-5 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 focus:outline-none text-xs"
                            title={showTabContent ? "Masquer le contenu" : "Afficher le contenu"}
                            style={{ zIndex: 30, position: 'relative', pointerEvents: 'auto' }}
                          >
                            {showTabContent ? "-" : "+"}
                          </button>
                          <span className="text-xs text-gray-600" style={{ zIndex: 30, position: 'relative', pointerEvents: 'auto' }}>
                            {showTabContent ? "Masquer" : "Afficher"} les informations client
                          </span>
                        </div>
                      </div>
                      <div>
                        <h3 className="text-lg font-medium text-gray-800 flex items-center">
                          Livraison
                          <span className={`ml-2 text-sm font-normal ${
                            selectedItems[0]?.L_TIRCODE && selectedItems[0]?.L_TIRCODE !== selectedItems[0]?.TIRCODE
                              ? 'text-orange-500'
                              : 'text-gray-600'
                          }`}>
                            (Code client livré : {selectedItems[0]?.L_TIRCODE || 'Non défini'})
                          </span>
                        </h3>
                      </div>
                    </div>

                    {showTabContent && (
                      <div className="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Bloc de facturation */}
                        <div className="border rounded-lg p-4 bg-gray-50 shadow-sm" style={getOverlayStyle()}>
                          {renderOverlay()}
                          <form className="space-y-2" aria-hidden={isConfigurationFinalized} tabIndex={-1}>
                            {/* Informations client - 2 colonnes */}
                            <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Client facturé</label>
                                <div className="flex items-center gap-2">
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.F_TIRCODE || ''}
                                    name="F_TIRCODE"
                                    className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100"
                                    readOnly
                                  />
                                  <button
                                    type="button"
                                    onClick={() => handleEditClientCode('facture')}
                                    className="p-1 text-gray-500 hover:text-gray-700"
                                    title="Modifier le code client facturé"
                                    {...addKeyboardDisabledProps()}
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                  </button>
                                </div>
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Société</label>
                                <div className="flex flex-1">
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.F_TIRSOCIETETYPE || ''}
                                    className="w-14 px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                                    readOnly
                                  />
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.F_TIRSOCIETE || ''}
                                    className="flex-1 px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                                    readOnly
                                  />
                                </div>
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Civilité</label>
                                <select
                                  className="flex-1 py-1 border border-gray-300 rounded text-sm"
                                  onFocus={() => loadEnumTypes(1)}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="F_CONTACT_TYPE"
                                  value={selectedItems[0]?.F_CONTACT_TYPE || ''}
                                  {...addKeyboardDisabledProps()}
                                >
                                  {selectedItems[0]?.F_CONTACT_TYPE && !enumTypes.civilites.length ? (
                                    <option value={selectedItems[0]?.F_CONTACT_TYPE}>{selectedItems[0]?.F_CONTACT_TYPE}</option>
                                  ) : (
                                    <option value="">Sélectionner...</option>
                                  )}
                                  {loadingCivilites ? (
                                    <option value="" disabled>Chargement...</option>
                                  ) : (
                                    enumTypes.civilites.map((type, index) => (
                                      <option key={index} value={type.libelle}>
                                        {type.libelle}
                                      </option>
                                    ))
                                  )}
                                </select>
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Nom</label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.F_CONTACT_NOM || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="F_CONTACT_NOM"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Prénom</label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.F_CONTACT_PRENOM || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="F_CONTACT_PRENOM"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              
                              {/* Adresse */}
                              <div className="flex items-center col-span-2">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Adresse</label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.F_ADRL1 || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="F_ADRL1"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              <div className="flex items-center col-span-2">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left"></label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.F_ADRL2 || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="F_ADRL2"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              <div className="flex items-center col-span-2">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left"></label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.F_ADRL3 || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="F_ADRL3"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              
                              <div className="flex items-center col-span-2 gap-4">
                                <div className="flex items-center flex-1">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Code postal</label>
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.F_ADRCODEPOSTAL || ''}
                                    name="F_ADRCODEPOSTAL"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                                <div className="flex items-center flex-[2]">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Ville</label>
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.F_ADRVILLE || ''}
                                    name="F_ADRVILLE"
                                    className="w-full px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                              </div>

                              <div className="flex items-center col-span-2 gap-4">
                                <div className="flex items-center flex-1">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Pays</label>
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.F_ADRPAYS || ''}
                                    name="F_ADRPAYS"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                                <div className="flex items-center flex-[2]">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Email</label>
                                  <input
                                    type="email"
                                    value={selectedItems[0]?.F_ADRMAIL || ''}
                                    name="F_ADRMAIL"
                                    className="w-full px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                              </div>

                              <div className="flex items-center col-span-2 gap-4">
                                <div className="flex items-center flex-1">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Téléphone</label>
                                  <input
                                    type="tel"
                                    value={selectedItems[0]?.F_ADRTEL || ''}
                                    name="F_ADRTEL"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                                <div className="flex items-center flex-[2]">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Portable</label>
                                  <input
                                    type="tel"
                                    value={selectedItems[0]?.F_ADRPORTABLE || ''}
                                    name="F_ADRPORTABLE"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm negative-margin"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                              </div>
                            </div>
                          </form>
                        </div>
                        
                        {/* Bloc de livraison */}
                        <div className="border rounded-lg p-4 bg-gray-50 shadow-sm" style={getOverlayStyle()}>
                          {renderOverlay()}
                          <form className="space-y-2" aria-hidden={isConfigurationFinalized} tabIndex={-1}>
                            {/* Informations client - 2 colonnes */}
                            <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Client livré</label>
                                <div className="flex items-center gap-2">
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.L_TIRCODE || ''}
                                    name="L_TIRCODE"
                                    className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm bg-gray-100"
                                    readOnly
                                  />
                                  <button
                                    type="button"
                                    onClick={() => handleEditClientCode('livre')}
                                    className="p-1 text-gray-500 hover:text-gray-700"
                                    title="Modifier le code client livré"
                                    {...addKeyboardDisabledProps()}
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                  </button>
                                </div>
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Société</label>
                                <div className="flex flex-1">
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.L_TIRSOCIETETYPE || ''}
                                    className="w-14 px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                                    readOnly
                                  />
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.L_TIRSOCIETE || ''}
                                    className="flex-1 px-2 py-1 border border-gray-300 rounded-md text-sm bg-gray-100"
                                    readOnly
                                  />
                                </div>
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Civilité</label>
                                <select
                                  className="flex-1 py-1 border border-gray-300 rounded text-sm"
                                  onFocus={() => loadEnumTypes(1)}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="L_CONTACT_TYPE"
                                  value={selectedItems[0]?.L_CONTACT_TYPE || ''}
                                  {...addKeyboardDisabledProps()}
                                >
                                  {selectedItems[0]?.L_CONTACT_TYPE && !enumTypes.civilites.length ? (
                                    <option value={selectedItems[0]?.L_CONTACT_TYPE}>{selectedItems[0]?.L_CONTACT_TYPE}</option>
                                  ) : (
                                    <option value="">Sélectionner...</option>
                                  )}
                                  {loadingCivilites ? (
                                    <option value="" disabled>Chargement...</option>
                                  ) : (
                                    enumTypes.civilites.map((type, index) => (
                                      <option key={index} value={type.libelle}>
                                        {type.libelle}
                                      </option>
                                    ))
                                  )}
                                </select>
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Nom</label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.L_CONTACT_NOM || ''}
                                  name="L_CONTACT_NOM"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Prénom</label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.L_CONTACT_PRENOM || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="L_CONTACT_PRENOM"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              
                              {/* Adresse */}
                              <div className="flex items-center col-span-2">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Adresse</label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.L_ADRL1 || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="L_ADRL1"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              <div className="flex items-center col-span-2">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left"></label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.L_ADRL2 || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="L_ADRL2"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              <div className="flex items-center col-span-2">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left"></label>
                                <input
                                  type="text"
                                  value={selectedItems[0]?.L_ADRL3 || ''}
                                  onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                  name="L_ADRL3"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  {...addKeyboardDisabledProps()}
                                />
                              </div>
                              
                              <div className="flex items-center col-span-2 gap-4">
                                <div className="flex items-center flex-1">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Code postal</label>
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.L_ADRCODEPOSTAL || ''}
                                    name="L_ADRCODEPOSTAL"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                                <div className="flex items-center flex-[2]">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Ville</label>
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.L_ADRVILLE || ''}
                                    name="L_ADRVILLE"
                                    className="w-full px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                              </div>

                              <div className="flex items-center col-span-2 gap-4">
                                <div className="flex items-center flex-1">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Pays</label>
                                  <input
                                    type="text"
                                    value={selectedItems[0]?.L_ADRPAYS || ''}
                                    name="L_ADRPAYS"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                                <div className="flex items-center flex-[2]">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Email</label>
                                  <input
                                    type="email"
                                    value={selectedItems[0]?.L_ADRMAIL || ''}
                                    name="L_ADRMAIL"
                                    className="w-full px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                              </div>

                              <div className="flex items-center col-span-2 gap-4">
                                <div className="flex items-center flex-1">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Téléphone</label>
                                  <input
                                    type="tel"
                                    value={selectedItems[0]?.L_ADRTEL || ''}
                                    name="L_ADRTEL"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                                <div className="flex items-center flex-[2]">
                                  <label className="block text-xs font-medium text-gray-600 w-28 text-left">Portable</label>
                                  <input
                                    type="tel"
                                    value={selectedItems[0]?.L_ADRPORTABLE || ''}
                                    name="L_ADRPORTABLE"
                                    className="w-32 px-1 py-1 border border-gray-300 rounded text-sm negative-margin"
                                    onChange={(e) => handleInputChange(selectedItems[0]?.PCVID + '-' + selectedItems[0]?.ARTID, e)}
                                    {...addKeyboardDisabledProps()}
                                  />
                                </div>
                              </div>
                            </div>
                          </form>
                        </div>
                      </div>
                    )}

                    {/* Bloc Abonnement - uniquement affiché quand isConfigurationFinalized est true */}
                    {isConfigurationFinalized && (
                      <div className="mb-4">
                        <h3 className="text-lg font-medium text-gray-800 flex items-center mb-2">
                          Abonnement
                        </h3>
                        <div className="border rounded-lg p-4 bg-gray-50 shadow-sm">
                          <form className="space-y-2">
                            <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Date de début</label>
                                <input
                                  type="date"
                                  name="dateDebut"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue={calculateSubscriptionDates().startDate}
                                />
                                <div className="flex items-center ml-2">
                                  <input
                                    type="checkbox"
                                    name="isActive"
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    defaultChecked={true}
                                    disabled={true}
                                  />
                                  <span className="ml-1 text-xs text-gray-600">Actif</span>
                                </div>
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Nombre d'échéances</label>
                                <input
                                  type="number"
                                  name="nombreEcheances"
                                  className="w-20 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue="1"
                                  min="0"
                                />
                                <div className="text-xs text-gray-600 ml-2">0</div>
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Périodicité</label>
                                <input
                                  type="number"
                                  name="periodicite"
                                  className="w-20 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue={calculateSubscriptionDates().days.toString() || "1"}
                                  min="1"
                                />
                                <select
                                  name="periodiciteType"
                                  className="ml-2 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue="J)"
                                >
                                  <option value="J">Jour(s)</option>
                                  <option value="S)">Semaine(s)</option>
                                  <option value="M">Mois</option>
                                  <option value="A">Année(s)</option>
                                </select>
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Dernière échéance</label>
                                <input
                                  type="date"
                                  name="derniereEcheance"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue={calculateSubscriptionDates().lastPaymentDate}
                                />
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Prochaine échéance</label>
                                <input
                                  type="date"
                                  name="prochaineEcheance"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue={calculateSubscriptionDates().nextPaymentDate}
                                />
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Alerte (en jours)</label>
                                <input
                                  type="number"
                                  name="alerteJours"
                                  className="w-20 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue="60"
                                  min="0"
                                />
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Renouvellement</label>
                                <select
                                  name="renouvellement"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue="C"
                                >
                                  <option value="C">Confirmation</option>
                                  <option value="T">Tacite</option>
                                  <option value="A">Aucun</option>
                                </select>
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Mode de révision</label>
                                <select
                                  name="modeRevision"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue="A"
                                >
                                  <option value="A">Automatique</option>
                                  <option value="M">Manuel</option>
                                  <option value="O">Outils</option>
                                </select>
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Date de résiliation</label>
                                <input
                                  type="date"
                                  name="dateResiliation"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  defaultValue={calculateSubscriptionDates().terminationDate}
                                />
                              </div>

                              <div className="flex items-center">
                                <label className="block text-xs font-medium text-gray-600 w-28 text-left">Transformation</label>
                                <select
                                  name="transformation"
                                  className="flex-1 px-1 py-1 border border-gray-300 rounded text-sm"
                                  value={selectedTransformation}
                                  onChange={(e) => setSelectedTransformation(e.target.value)}
                                >
                                  {loadingTransformationCodes ? (
                                    <option value="" disabled>Chargement...</option>
                                  ) : (
                                    transformationCodes.map((code, index) => (
                                      <option key={index} value={code.code}>
                                        {code.code} - {code.libelle}
                                      </option>
                                    ))
                                  )}
                                </select>
                              </div>
                            </div>
                          </form>
                        </div>
                      </div>
                    )}

                    {/* Tableau des éléments sélectionnés */}
                    <div className="mt-8">
                      <h3 className="text-base font-medium text-gray-700 mb-2">Éléments sélectionnés</h3>
                      <div className="overflow-x-auto mb-4 relative" style={getOverlayStyle()}>
                        {renderOverlay()}
                        <DndContext 
                          sensors={sensors}
                          collisionDetection={closestCenter}
                          onDragEnd={handleDragEnd}
                        >
                          <table className="min-w-full bg-white border border-gray-200" {...(isConfigurationFinalized ? addKeyboardDisabledProps() : {})}>
                            <thead className="bg-gray-50">
                              <tr>
                                <th className="w-8 px-2"></th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Code article</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro de série</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Divers ligne</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date début</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date fin</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Code S/N</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">QR</th>
                                <th className="py-2 px-4 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                              <tr>
                                <td></td>
                                <td colSpan={8}></td>
                                <td className="py-2 px-2">
                                  <div className="flex justify-end">
                                    <button
                                      type="button"
                                      onClick={() => addComment('top')}
                                      className="w-6 h-6 flex items-center justify-center bg-slate-400 text-white rounded-full hover:bg-slate-500"
                                      title="Ajouter un commentaire au début"
                                      disabled={isConfigurationFinalized}
                                      style={{ opacity: isConfigurationFinalized ? 0.5 : 1 }}
                                      {...addKeyboardDisabledProps()}
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                                      </svg>
                                    </button>
                                  </div>
                                </td>
                              </tr>
                              <SortableContext 
                                items={tableRows.map(row => row.id)} 
                                strategy={verticalListSortingStrategy}
                              >
                                {tableRows.map((row) => {
                                  if (row.id === 'top-button') {
                                    return null;
                                  }
                                  
                                  if (row.type === 'comment') {
                                    return (
                                      <DraggableRow key={row.id} id={row.id}>
                                        <td colSpan={8} className="py-2 px-4">
                                          <input
                                            type="text"
                                            value={row.content || ''}
                                            onChange={(e) => handleCommentChange(row.id, e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            placeholder="Saisissez votre commentaire ici..."
                                          />
                                        </td>
                                        <td className="py-2 px-2">
                                          <div className="flex justify-end gap-2">
                                            <button
                                              type="button"
                                              onClick={() => removeCommentLine(row.id)}
                                              className="text-red-600 hover:text-red-800 focus:outline-none"
                                              title="Supprimer ce commentaire"
                                              disabled={isConfigurationFinalized}
                                              style={{ opacity: isConfigurationFinalized ? 0.5 : 1 }}
                                              {...addKeyboardDisabledProps()}
                                            >
                                              <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                              </svg>
                                            </button>
                                            <button
                                              type="button"
                                              onClick={() => addComment(row.id)}
                                              className="w-6 h-6 flex items-center justify-center bg-slate-400 text-white rounded-full hover:bg-slate-500"
                                              title="Ajouter un commentaire après celui-ci"
                                              disabled={isConfigurationFinalized}
                                              style={{ opacity: isConfigurationFinalized ? 0.5 : 1 }}
                                              {...addKeyboardDisabledProps()}
                                            >
                                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                                              </svg>
                                            </button>
                                          </div>
                                        </td>
                                      </DraggableRow>
                                    );
                                  } else {
                                    const item = row.content;
                                    return (
                                      <DraggableRow key={row.id} id={row.id}>
                                        <td className="py-2 px-4 text-sm text-left">{item.ARTCODE}</td>
                                        <td className="py-2 px-4 text-sm text-left">{item.ARTDESIGNATION}</td>
                                        <td className="py-2 px-4 text-sm text-left">{item.PLVNUMSERIE}</td>
                                        <td className="py-2 px-4 text-sm text-left">{item.PLVDIVERS}</td>
                                        <td className="py-2 px-4 text-sm text-center">{formatDateFr(item.DATEDEBUT)}</td>
                                        <td className="py-2 px-4 text-sm text-center">{formatDateFr(item.DATEFIN)}</td>
                                        <td className="py-2 px-4 text-sm text-left">
                                          {item.CODESN ? (
                                            <button 
                                              onClick={(e) => showFullCodeSN(item.CODESN || '', e)}
                                              className="max-w-[150px] truncate text-blue-600 hover:underline text-center"
                                              title="Cliquez pour voir le code complet"
                                            >
                                              {item.CODESN}
                                            </button>
                                          ) : (
                                            '-'
                                          )}
                                        </td>
                                        <td className="py-2 px-4 text-sm">{item.QRCODE === 'O' ? 'O' : 'N'}</td>
                                        <td className="py-2 px-2">
                                          <div className="flex justify-end">
                                            <button
                                              type="button"
                                              onClick={() => addComment(row.id)}
                                              className="w-6 h-6 flex items-center justify-center bg-slate-400 text-white rounded-full hover:bg-slate-500"
                                              title="Ajouter un commentaire ici"
                                              disabled={isConfigurationFinalized}
                                              style={{ opacity: isConfigurationFinalized ? 0.5 : 1 }}
                                              {...addKeyboardDisabledProps()}
                                            >
                                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                                              </svg>
                                            </button>
                                          </div>
                                        </td>
                                      </DraggableRow>
                                    );
                                  }
                                })}
                              </SortableContext>
                            </tbody>
                          </table>
                        </DndContext>
                      </div>

                      {/* Bouton Enregistrer */}
                      <form onSubmit={(e) => prepareSubmit(selectedItems[0].PCVID + '-' + selectedItems[0].ARTID, e)}>
                        {errorMessage && (
                          <div className="mb-4">
                            <div className="p-4 rounded-lg border bg-red-50 border-red-200 text-red-800">
                              <div className="flex items-start">
                                <div className="flex-shrink-0 mt-0.5">
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                  </svg>
                                </div>
                                <div className="ml-3 flex-grow">
                                  <pre className="text-sm font-medium whitespace-pre-wrap break-words">
                                    {errorMessage}
                                  </pre>
                                  {formatErrorMessageWithMailto(errorMessage)?.showMailto && (
                                    <div className="mt-2">
                                      <a 
                                        href={formatErrorMessageWithMailto(errorMessage)?.mailtoUrl} 
                                        className="text-blue-600 hover:text-blue-800 underline"
                                      >
                                        Contacter le support par email
                                      </a>
                                    </div>
                                  )}
                                </div>
                              </div>
                            </div>
                          </div>
                        )}
                        <div className="flex justify-center">
                          {isConfigurationFinalized ? (
                            <>
                              <button
                                type="button"
                                onClick={handleCancelFinalization}
                                className="px-4 py-2 mr-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                                disabled={isSubmitting}
                              >
                                Retour à la configuration
                              </button>
                              <button
                                type="button"
                                onClick={handleFinalizeSubscription}
                                className="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 flex items-center justify-center disabled:opacity-70 disabled:cursor-not-allowed"
                                disabled={isSubmitting}
                              >
                                {isSubmitting ? (
                                  <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Validation en cours...
                                  </>
                                ) : (
                                  "Valider l'abonnement"
                                )}
                              </button>
                            </>
                          ) : (
                            <button
                              type="submit"
                              className="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700"
                            >
                              Finaliser la configuration
                            </button>
                          )}
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="bg-yellow-50 border border-yellow-200 rounded p-4">
              <p className="text-yellow-700">
                Aucun abonnement disponible.
              </p>
            </div>
          )}
          
          {/* Modal pour afficher le code S/N complet */}
          {showCodeModal && (
            <div className="fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-50">
              <div className="bg-white rounded-lg p-6 w-full max-w-2xl">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-xl font-semibold">Code S/N complet</h3>
                  <button 
                    onClick={closeCodeModal}
                    className="text-gray-500 hover:text-gray-700"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                
                <div className="border rounded-md p-4 bg-gray-50 mb-4 overflow-auto max-h-96">
                  <p className="font-mono text-sm break-all">{currentCodeSN}</p>
                </div>
                
                <div className="flex justify-end">
                  <button
                    onClick={() => {
                      copyToClipboard(currentCodeSN);
                      closeCodeModal();
                    }}
                    className="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 mr-2"
                  >
                    Copier
                  </button>
                  <button
                    onClick={closeCodeModal}
                    className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Fermer
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Modal d'édition du code client */}
          {showEditClientModal && (
            <div className="fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-50">
              <div className="bg-white rounded-lg p-6 w-full max-w-md">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-xl font-semibold">
                    Modifier le code {editingClientType === 'final' ? 'client final' : editingClientType === 'facture' ? 'client facturé' : 'client livré'}
                  </h3>
                  <button 
                    onClick={() => setShowEditClientModal(false)}
                    className="text-gray-500 hover:text-gray-700"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Nouveau code client
                  </label>
                  <input
                    type="text"
                    value={newClientCode}
                    onChange={handleClientCodeChange}
                    className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 ${
                      clientValidation.message ? (clientValidation.isValid ? 'border-green-500' : 'border-red-500') : 'border-gray-300'
                    }`}
                    placeholder="Entrez le nouveau code client"
                  />
                  {isCheckingClient && (
                    <div className="mt-2 text-sm text-gray-500">
                      Vérification en cours...
                    </div>
                  )}
                  {clientValidation.message && !isCheckingClient && (
                    <div className={`mt-2 text-sm ${clientValidation.isValid ? 'text-green-600' : 'text-red-600'}`}>
                      {clientValidation.message}
                    </div>
                  )}
                </div>
                
                <div className="flex justify-end space-x-3">
                  <button
                    onClick={() => setShowEditClientModal(false)}
                    className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Annuler
                  </button>
                  <button
                    onClick={handleSaveClientCode}
                    disabled={!clientValidation.isValid || isCheckingClient}
                    className={`px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white ${
                      clientValidation.isValid && !isCheckingClient
                        ? 'bg-blue-600 hover:bg-blue-700'
                        : 'bg-gray-400 cursor-not-allowed'
                    }`}
                  >
                    Enregistrer
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
      <Toaster />
    </div>
  );
}

