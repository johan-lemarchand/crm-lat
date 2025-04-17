import React from 'react';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from '@/components/ui/button';
import { OdfStep } from './types';

interface StepsModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  steps: OdfStep[];
}

const StepsModal: React.FC<StepsModalProps> = ({ 
  open, 
  onOpenChange, 
  steps 
}) => {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[90vw] max-h-[90vh] w-full">
        <DialogHeader>
          <DialogTitle>Détails des étapes</DialogTitle>
          <DialogDescription>
            Contenu JSON formaté des étapes de l'exécution
          </DialogDescription>
        </DialogHeader>
        <div className="bg-gray-100 p-4 rounded-md overflow-auto h-[calc(90vh-200px)]">
          <pre className="text-xs font-mono whitespace-pre-wrap break-all">
            {JSON.stringify(steps, null, 2)}
          </pre>
        </div>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>Fermer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

export default StepsModal; 