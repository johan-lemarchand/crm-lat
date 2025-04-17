import {
    Body,
    Container,
    Head,
    Heading,
    Html,
    Preview,
    Text,
    Tailwind,
    Section,
    Row,
    Column,
    Hr,
    render
} from '@react-email/components';

interface CurrencyDetails {
    last_update: string;
    old_rate: number;
    new_rate: number;
    summary?: {
        articles_updated: number;
    };
}

interface CurrencySyncEmailProps {
    status: 'success' | 'error' | 'warning';
    executionDate: string;
    total_devises?: number;
    mises_a_jour?: number;
    erreurs?: number;
    currencies?: Record<string, CurrencyDetails>;
    exception?: string;
}

const CurrencySyncEmail = ({
    status,
    executionDate,
    total_devises = 0,
    mises_a_jour = 0,
    erreurs = 0,
    currencies = {},
    exception
}: CurrencySyncEmailProps) => {
    const statusColors = {
        success: 'text-green-500',
        error: 'text-red-500',
        warning: 'text-yellow-500'
    };

    const statusText = {
        success: 'Succès',
        error: 'Erreur',
        warning: 'Attention'
    };

    const formatRate = (rate: number): string => {
        return rate.toFixed(6);
    };

    return (
        <Html>
            <Head />
            <Preview>Rapport de synchronisation des devises {status === 'success' ? 'réussie' : 'en erreur'}</Preview>
            <Tailwind>
                <Body className="bg-white my-12 mx-auto font-sans">
                    <Container className="border border-solid border-[#eaeaea] rounded my-[40px] mx-auto p-[20px] max-w-[600px]">
                        <Heading className="text-black text-[24px] font-normal text-center p-0 my-[30px] mx-0">
                            Rapport de synchronisation des devises
                        </Heading>
                        
                        <Section className="bg-gray-50 p-4 rounded-lg mb-6">
                            <Row>
                                <Column>
                                    <Text className="text-black text-[14px] leading-[24px] font-bold">
                                        Résumé
                                    </Text>
                                </Column>
                            </Row>
                            <Row>
                                <Column className="pe-2">
                                    <Text className="text-black text-[14px] leading-[24px]">
                                        Statut : <strong className={statusColors[status]}>{statusText[status]}</strong>
                                    </Text>
                                </Column>
                                <Column>
                                    <Text className="text-black text-[14px] leading-[24px]">
                                        Date d'exécution : <strong>{executionDate}</strong>
                                    </Text>
                                </Column>
                            </Row>
                            
                            {!exception && (
                                <Row>
                                    <Column className="pe-2">
                                        <Text className="text-black text-[14px] leading-[24px]">
                                            Total devises : <strong>{total_devises}</strong>
                                        </Text>
                                    </Column>
                                    <Column className="pe-2">
                                        <Text className="text-black text-[14px] leading-[24px]">
                                            Mises à jour : <strong>{mises_a_jour}</strong>
                                        </Text>
                                    </Column>
                                    <Column>
                                        <Text className="text-black text-[14px] leading-[24px]">
                                            Erreurs : <strong className={erreurs > 0 ? 'text-red-500' : 'text-green-500'}>
                                                {erreurs}
                                            </strong>
                                        </Text>
                                    </Column>
                                </Row>
                            )}
                            
                            {exception && (
                                <Row>
                                    <Column>
                                        <Text className="text-red-500 text-[14px] leading-[24px]">
                                            Erreur critique : {exception}
                                        </Text>
                                    </Column>
                                </Row>
                            )}
                        </Section>
                        
                        {Object.keys(currencies).length > 0 && (
                            <Section>
                                <Text className="text-black text-[16px] leading-[24px] font-bold mb-4">
                                    Détails des devises mises à jour
                                </Text>
                                
                                {Object.entries(currencies).map(([currency, details], index) => (
                                    <Section key={currency} className="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                                        <Row>
                                            <Column>
                                                <Text className="text-black text-[14px] leading-[24px] font-bold">
                                                    Devise : {currency}
                                                </Text>
                                            </Column>
                                        </Row>
                                        <Row>
                                            <Column>
                                                <Text className="text-black text-[14px] leading-[24px]">
                                                    Dernière mise à jour avant ce jour : {new Date(details.last_update).toLocaleString()}
                                                </Text>
                                            </Column>
                                        </Row>
                                        <Row>
                                            <Column className="pe-2">
                                                <Text className="text-black text-[14px] leading-[24px]">
                                                    Ancien taux : 1 {currency} = {details.old_rate} EUR
                                                </Text>
                                            </Column>
                                            <Column>
                                                <Text className="text-black text-[14px] leading-[24px]">
                                                    Nouveau taux : 1 {currency} = {formatRate(details.new_rate)} EUR
                                                </Text>
                                            </Column>
                                        </Row>
                                        
                                        {details.summary && details.summary.articles_updated > 0 && (
                                            <Row>
                                                <Column>
                                                    <Text className="text-black text-[14px] leading-[24px]">
                                                        Articles mis à jour : <strong>{details.summary.articles_updated}</strong>
                                                    </Text>
                                                </Column>
                                            </Row>
                                        )}
                                    </Section>
                                ))}
                            </Section>
                        )}
                        
                        <Hr className="border border-solid border-[#eaeaea] my-[26px] mx-0 w-full" />
                        
                        <Text className="text-[12px] text-[#666666] leading-[24px] text-center">
                            Ce message a été généré automatiquement. Merci de ne pas y répondre.
                        </Text>
                    </Container>
                </Body>
            </Tailwind>
        </Html>
    );
};

export const renderCurrencySyncEmail = async (props: CurrencySyncEmailProps): Promise<string> => {
    try {
        const html = await render(<CurrencySyncEmail {...props} />);
        return Array.isArray(html) ? html.join('') : html;
    } catch (error) {
        console.error('Erreur lors du rendu de l\'email:', error);
        
        // Fallback en cas d'erreur
        return `
            <div>
                <h1>Rapport de synchronisation des devises</h1>
                <p>Statut : ${props.status}</p>
                <p>Date d'exécution : ${props.executionDate}</p>
                ${props.exception ? `<p style="color: red;">Erreur critique : ${props.exception}</p>` : ''}
            </div>
        `;
    }
}; 