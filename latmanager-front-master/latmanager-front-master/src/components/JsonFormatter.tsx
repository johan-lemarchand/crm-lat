import React, { useState } from 'react';
import { ChevronRight, ChevronDown } from 'lucide-react';

type JsonValue = 
  | string 
  | number 
  | boolean 
  | null 
  | JsonValue[] 
  | { [key: string]: JsonValue };

interface JsonFormatterProps {
  data: string;
  maxHeight?: string;
}

interface CollapsibleProps {
  isOpen: boolean;
  onToggle: () => void;
  preview: string;
  children: React.ReactNode;
}

function Collapsible({ isOpen, onToggle, preview, children }: CollapsibleProps) {
  return (
    <span className="block">
      <span onClick={onToggle} className="cursor-pointer inline-flex items-center">
        {isOpen ? (
          <ChevronDown className="h-4 w-4 text-gray-500" />
        ) : (
          <ChevronRight className="h-4 w-4 text-gray-500" />
        )}
        {!isOpen && <span className="text-gray-400 ml-1">{preview}</span>}
      </span>
      {isOpen && children}
    </span>
  );
}

export function JsonFormatter({ 
  data, 
  maxHeight = '400px'
}: JsonFormatterProps) {
  const [openNodes, setOpenNodes] = useState<Set<string>>(new Set(['root']));

  const toggleNode = (path: string) => {
    const newOpenNodes = new Set(openNodes);
    if (newOpenNodes.has(path)) {
      newOpenNodes.delete(path);
    } else {
      newOpenNodes.add(path);
    }
    setOpenNodes(newOpenNodes);
  };

  const formatJson = (data: JsonValue, level: number = 0, path: string = 'root'): JSX.Element => {
    const isOpen = openNodes.has(path);

    if (Array.isArray(data)) {
      if (data.length === 0) return <span className="text-blue-500">[]</span>;
      
      const preview = `Array(${data.length})`;
      
      return (
        <span>
          <Collapsible
            isOpen={isOpen}
            onToggle={() => toggleNode(path)}
            preview={preview}
          >
            <span>
              <span className="text-blue-500">[</span>
              {data.map((item, index) => (
                <span key={index} className="block ml-4">
                  {formatJson(item, level + 1, `${path}.${index}`)}
                  <span className="text-gray-300">{index < data.length - 1 ? ',' : ''}</span>
                </span>
              ))}
              <span className="block ml-4">
                <span className="text-blue-500">]</span>
              </span>
            </span>
          </Collapsible>
        </span>
      );
    }
    
    if (typeof data === 'object' && data !== null) {
      const entries = Object.entries(data);
      if (entries.length === 0) return <span className="text-yellow-500">{'{}'}</span>;
      
      const preview = `Object(${entries.length})`;
      
      return (
        <span>
          <Collapsible
            isOpen={isOpen}
            onToggle={() => toggleNode(path)}
            preview={preview}
          >
            <span>
              <span className="text-yellow-500">{'{'}</span>
              {entries.map(([key, value], index) => (
                <span key={key} className="block ml-4">
                  <span className="text-emerald-500">"{key}"</span>
                  <span className="text-gray-300">: </span>
                  {formatJson(value as JsonValue, level + 1, `${path}.${key}`)}
                  <span className="text-gray-300">{index < entries.length - 1 ? ',' : ''}</span>
                </span>
              ))}
              <span className="block ml-4">
                <span className="text-yellow-500">{'}'}</span>
              </span>
            </span>
          </Collapsible>
        </span>
      );
    }
    
    if (typeof data === 'string') {
      return <span className="text-cyan-500">"{data}"</span>;
    }
    
    if (typeof data === 'number') {
      return <span className="text-violet-500">{data}</span>;
    }
    
    if (typeof data === 'boolean') {
      return <span className="text-red-500">{data.toString()}</span>;
    }
    
    if (data === null) {
      return <span className="text-gray-500">null</span>;
    }
    
    return <span>{String(data)}</span>;
  };

  try {
    const jsonData = JSON.parse(data);
    return (
      <div className="w-full rounded border border-gray-200 bg-gray-950">
        <div className="overflow-auto" style={{ maxHeight }}>
          <div className="p-2 font-mono text-sm" style={{ textAlign: 'left' }}>
            <div className="pl-2">
              {formatJson(jsonData)}
            </div>
          </div>
        </div>
      </div>
    );
  } catch (error) {
    return (
      <div className="text-red-500 p-4 border border-red-200 rounded bg-red-50">
        Format JSON invalide : {(error as Error).message}
      </div>
    );
  }
} 