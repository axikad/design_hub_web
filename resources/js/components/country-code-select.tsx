import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const countryCodes = [
    ['India', '+91'],
    ['United States / Canada', '+1'],
    ['United Kingdom', '+44'],
    ['United Arab Emirates', '+971'],
    ['Australia', '+61'],
    ['Bangladesh', '+880'],
    ['Brazil', '+55'],
    ['China', '+86'],
    ['France', '+33'],
    ['Germany', '+49'],
    ['Hong Kong', '+852'],
    ['Indonesia', '+62'],
    ['Italy', '+39'],
    ['Japan', '+81'],
    ['Malaysia', '+60'],
    ['Nepal', '+977'],
    ['New Zealand', '+64'],
    ['Pakistan', '+92'],
    ['Qatar', '+974'],
    ['Saudi Arabia', '+966'],
    ['Singapore', '+65'],
    ['South Africa', '+27'],
    ['South Korea', '+82'],
    ['Sri Lanka', '+94'],
    ['Thailand', '+66'],
] as const;

type Props = { value: string; onValueChange: (value: string) => void; className?: string };

export default function CountryCodeSelect({ value, onValueChange, className }: Props) {
    return (
        <Select value={value} onValueChange={onValueChange}>
            <SelectTrigger className={className} aria-label="Country code">
                <SelectValue placeholder="Code" />
            </SelectTrigger>
            <SelectContent>
                {countryCodes.map(([country, code]) => (
                    <SelectItem key={`${country}-${code}`} value={code}>
                        {country} ({code})
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
