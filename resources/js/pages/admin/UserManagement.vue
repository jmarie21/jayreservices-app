<script setup lang="ts">
import AddUserForm from '@/components/forms/AddUserForm.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, User, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast, Toaster } from 'vue-sonner';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '../../components/ui/alert-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: '/user-mgmt',
    },
];

const page = usePage<AppPageProps<{ users: User[] }>>();
const users = computed(() => page.props.users);

const loading = ref(false);
const selectedUser = ref<User | null>(null);
const showForm = ref(false);
const showDeleteModal = ref(false);

const openAddForm = () => {
    selectedUser.value = null;
    showForm.value = true;
};

const openEditForm = (user: any) => {
    selectedUser.value = user;
    showForm.value = true;
};

const confirmDelete = (user: User) => {
    selectedUser.value = user;
    showDeleteModal.value = true;
};

const deleteUser = () => {
    if (!selectedUser.value) return;
    router.delete(route('user-mgmt.destroy', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('User deleted successfully.', { position: 'top-right' });
            showDeleteModal.value = false;
            selectedUser.value = null;
        },
        onError: () => {
            toast.error('Failed to delete user.');
        },
    });
};
</script>

<template>
    <Head title="User Management" />
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex justify-between gap-4 rounded-xl p-4">
            <div>
                <h1 class="text-2xl font-bold">Users</h1>
            </div>

            <div>
                <Button @click="openAddForm">Add New User</Button>
            </div>
        </div>

        <div class="p-4">
            <Table>
                <TableCaption>A list of your recent invoices.</TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[240px]"> Name </TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead> Created At </TableHead>
                        <TableHead> Action </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody v-for="user in users">
                    <TableRow>
                        <TableCell class="font-medium"> {{ user.name }} </TableCell>
                        <TableCell>{{ user.email }}</TableCell>
                        <TableCell>{{ user.role }}</TableCell>
                        <TableCell> {{ new Date(user.created_at).toLocaleDateString() }} </TableCell>
                        <TableCell class="space-x-4">
                            <Button @click="openEditForm(user)">Edit</Button>
                            <Button variant="destructive" @click="confirmDelete(user)">Delete</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <AddUserForm :open="showForm" :user="selectedUser" @close="showForm = false" />

        <!-- Delete Confirmation Modal -->
        <AlertDialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete User</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete
                        <strong>{{ selectedUser?.name }}</strong
                        >? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="showDeleteModal = false">Cancel</AlertDialogCancel>
                    <Button variant="destructive" @click="deleteUser">Delete</Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
