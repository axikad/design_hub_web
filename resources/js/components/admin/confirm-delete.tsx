import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Trash2, TriangleAlert } from 'lucide-react';
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
            <Button type="button" variant="destructive" size="sm" className="rounded-lg" onClick={() => setOpen(true)}>
                <Trash2 className="size-4" />
                {triggerLabel}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <div className="mb-2 flex size-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
                            <TriangleAlert className="size-5" />
                        </div>
                        <DialogTitle className="text-left">{title}</DialogTitle>
                        <DialogDescription className="text-left leading-6">{description}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" className="rounded-xl" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="rounded-xl"
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
