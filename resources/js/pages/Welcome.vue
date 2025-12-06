<script setup lang="ts">
import HomeNavbar from '@/components/home/HomeNavbar.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

// shadcn components
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

const page = usePage();

// Determine the route for Get Started / Dashboard dynamically
const getStartedRoute = computed(() => {
    const user = page.props.auth.user;

    if (!user) return '/login'; // Not authenticated → login

    // Authenticated → route based on role
    return user.role === 'admin' ? '/admin-dashboard' : user.role === 'client' ? '/projects' : user.role === 'editor' ? '/editor-dashboard' : '/';
});
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a]">
        <!-- Navbar -->
        <HomeNavbar />

        <!-- Dashboard-style Hero -->
        <div class="flex flex-1 items-center justify-center px-6">
            <Card class="w-full max-w-2xl shadow-lg">
                <CardHeader class="flex flex-col items-center text-center">
                    <img src="/jayreblack.png" alt="JayRE Logo" class="mb-4 h-50" />
                    <CardTitle class="text-2xl font-bold lg:text-4xl"> Welcome to JayRE Project System </CardTitle>
                    <p class="text-md mt-2 text-gray-600 dark:text-gray-300">A unified workspace to manage projects and track progress.</p>
                </CardHeader>
                <CardContent class="mt-6 flex flex-col items-center space-y-4 sm:flex-row sm:justify-center sm:space-y-0 sm:space-x-4">
                    <!-- Dynamic Get Started / Dashboard link -->
                    <Link :href="getStartedRoute" class="w-full sm:w-auto">
                        <Button size="lg" class="w-full sm:w-auto"> Get Started </Button>
                    </Link>

                    <!-- External link -->
                    <a href="https://jayreservices.com" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto">
                        <Button variant="outline" size="lg" class="w-full sm:w-auto"> View Services </Button>
                    </a>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
