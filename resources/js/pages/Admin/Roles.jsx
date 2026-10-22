import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Check, Lock, Minus, Save, ShieldCheck, Undo2, Users } from 'lucide-react';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Rôles et permissions : une fiche par rôle (interrupteurs regroupés par
 * domaine) et une matrice de synthèse. Chaque enregistrement est tracé dans
 * le journal d'activité.
 */
export default function Roles({ groups, roles, lockedPermission }) {
    const totalPermissions = groups.reduce((sum, group) => sum + group.permissions.length, 0);

    return (
        <TooltipProvider>
            <Head title="Rôles et permissions" />

            <div className="mb-6">
                <p className="kicker mb-1">Administration</p>
                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Rôles et permissions</h1>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {totalPermissions} permissions réparties en {groups.length} domaines. Chaque modification est enregistrée dans le journal d’activité.
                </p>
            </div>

            <Tabs defaultValue={String(roles[0]?.id)} className="gap-6">
                <TabsList className="h-auto flex-wrap">
                    {roles.map((role) => (
                        <TabsTrigger key={role.id} value={String(role.id)} className="px-4">
                            {role.label}
                            <Badge variant="secondary" className="ml-1 tabular-nums">{role.users}</Badge>
                        </TabsTrigger>
                    ))}
                    <TabsTrigger value="matrix" className="px-4">Matrice</TabsTrigger>
                </TabsList>

                {roles.map((role) => (
                    <TabsContent key={role.id} value={String(role.id)}>
                        <RoleEditor role={role} groups={groups} total={totalPermissions} lockedPermission={lockedPermission} />
                    </TabsContent>
                ))}

                <TabsContent value="matrix">
                    <Matrix groups={groups} roles={roles} />
                </TabsContent>
            </Tabs>
        </TooltipProvider>
    );
}

function RoleEditor({ role, groups, total, lockedPermission }) {
    const [selected, setSelected] = useState(() => new Set(role.permissions));
    const [saving, setSaving] = useState(false);

    const dirty = useMemo(() => {
        const initial = new Set(role.permissions);

        return initial.size !== selected.size || [...selected].some((id) => !initial.has(id));
    }, [role.permissions, selected]);

    const isLocked = (id) => role.isOwnRole && id === lockedPermission;

    const toggle = (id, checked) =>
        setSelected((current) => {
            const next = new Set(current);
            checked ? next.add(id) : next.delete(id);

            return next;
        });

    const toggleGroup = (group, checked) =>
        setSelected((current) => {
            const next = new Set(current);
            group.permissions.forEach((permission) => (checked || isLocked(permission.id) ? next.add(permission.id) : next.delete(permission.id)));

            return next;
        });

    const save = () =>
        router.put(role.updateUrl, { permissions: [...selected] }, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });

    return (
        <div className="grid items-start gap-6 xl:grid-cols-[320px_1fr]">
            <Card className="xl:sticky xl:top-24">
                <CardHeader>
                    <span className="grid size-12 place-items-center rounded-xl bg-gradient-to-br from-cyan-400 to-cyan-600 text-white shadow-lg shadow-cyan-500/30">
                        <ShieldCheck className="size-6" aria-hidden="true" />
                    </span>
                    <CardTitle className="mt-3 text-xl">{role.label}</CardTitle>
                    <CardDescription>{role.description}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Users className="size-4" /> {role.activeUsers} utilisateur(s) actif(s) sur {role.users}
                    </div>
                    <div>
                        <div className="mb-1.5 flex justify-between">
                            <span className="text-muted-foreground">Permissions accordées</span>
                            <span className="font-bold tabular-nums">{selected.size} / {total}</span>
                        </div>
                        <Progress value={(selected.size / total) * 100} aria-label="Permissions accordées" />
                    </div>
                    <div className="flex gap-2 pt-2">
                        <Button onClick={save} disabled={!dirty || saving} className="flex-1"><Save /> {saving ? 'Enregistrement…' : 'Enregistrer'}</Button>
                        <Button variant="outline" size="icon" onClick={() => setSelected(new Set(role.permissions))} disabled={!dirty || saving} aria-label="Annuler les modifications"><Undo2 /></Button>
                    </div>
                    {dirty && <p className="text-xs font-medium text-amber-700 dark:text-amber-300">Modifications non enregistrées.</p>}
                </CardContent>
            </Card>

            <Card className="py-2">
                <CardContent className="px-2 sm:px-4">
                    <Accordion type="multiple" defaultValue={groups.map((group) => group.name)}>
                        {groups.map((group) => {
                            const granted = group.permissions.filter((permission) => selected.has(permission.id)).length;
                            const all = granted === group.permissions.length;

                            return (
                                <AccordionItem key={group.name} value={group.name}>
                                    <div className="flex items-center gap-3">
                                        <AccordionTrigger className="flex-1 text-base hover:no-underline">
                                            <span className="flex items-center gap-2">
                                                {group.name}
                                                <Badge variant={granted > 0 ? 'default' : 'secondary'} className="tabular-nums">{granted}/{group.permissions.length}</Badge>
                                            </span>
                                        </AccordionTrigger>
                                        <Button variant="ghost" size="sm" onClick={() => toggleGroup(group, !all)}>
                                            {all ? 'Tout retirer' : 'Tout accorder'}
                                        </Button>
                                    </div>
                                    <AccordionContent>
                                        <ul className="divide-y rounded-lg border">
                                            {group.permissions.map((permission) => {
                                                const id = `perm-${role.id}-${permission.id}`;
                                                const locked = isLocked(permission.id);

                                                return (
                                                    <li key={permission.id} className="flex items-start gap-4 px-4 py-3">
                                                        <div className="min-w-0 flex-1">
                                                            <label htmlFor={id} className="flex items-center gap-2 font-medium">
                                                                {permission.label}
                                                                {locked && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild><Lock className="size-3.5 text-muted-foreground" aria-label="Permission verrouillée" /></TooltipTrigger>
                                                                        <TooltipContent>Vous ne pouvez pas retirer cette permission à votre propre rôle.</TooltipContent>
                                                                    </Tooltip>
                                                                )}
                                                            </label>
                                                            <p className="text-xs text-muted-foreground">{permission.description}</p>
                                                            <code className="text-[11px] text-muted-foreground/80">{permission.name}</code>
                                                        </div>
                                                        <Switch id={id} checked={selected.has(permission.id)} disabled={locked} onCheckedChange={(checked) => toggle(permission.id, checked)} />
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </AccordionContent>
                                </AccordionItem>
                            );
                        })}
                    </Accordion>
                </CardContent>
            </Card>
        </div>
    );
}

function Matrix({ groups, roles }) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="pl-6">Permission</TableHead>
                            {roles.map((role) => <TableHead key={role.id} className="text-center">{role.label}</TableHead>)}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {groups.map((group) => [
                            <TableRow key={group.name} className="bg-muted/50 hover:bg-muted/50">
                                <TableCell colSpan={roles.length + 1} className="pl-6 text-xs font-bold tracking-wider text-muted-foreground uppercase">{group.name}</TableCell>
                            </TableRow>,
                            ...group.permissions.map((permission) => (
                                <TableRow key={permission.id}>
                                    <TableCell className="pl-6">
                                        <div className="font-medium">{permission.label}</div>
                                        <code className="text-[11px] text-muted-foreground">{permission.name}</code>
                                    </TableCell>
                                    {roles.map((role) => {
                                        const granted = role.permissions.includes(permission.id);

                                        return (
                                            <TableCell key={role.id} className="text-center">
                                                <span className={cn('inline-grid size-6 place-items-center rounded-full', granted ? 'bg-primary/15 text-primary' : 'text-muted-foreground/40')}>
                                                    {granted ? <Check className="size-4" aria-label="Accordée" /> : <Minus className="size-4" aria-label="Non accordée" />}
                                                </span>
                                            </TableCell>
                                        );
                                    })}
                                </TableRow>
                            )),
                        ])}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
