import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useState } from 'react';

type Props = {
    title: string;
    description: string;
    onConfirm: () => void;
    triggerLabel?: string;
};

export function ConfirmDelete({ title, description, onConfirm, triggerLabel = 'Delete' }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button type="button" variant="destructive" size="sm" onClick={() => setOpen(true)}>
                {triggerLabel}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                onConfirm();
                                setOpen(false);
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
