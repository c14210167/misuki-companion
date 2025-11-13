// Register slash commands with Discord
// Run this once: node register-commands.js

require('dotenv').config();
const { REST, Routes, SlashCommandBuilder } = require('discord.js');

const commands = [
    new SlashCommandBuilder()
        .setName('selfie')
        .setDescription('Admin: Control selfie permissions')
        .addStringOption(option =>
            option.setName('mode')
                .setDescription('Selfie permission mode')
                .setRequired(true)
                .addChoices(
                    { name: 'All (everyone can request)', value: 'all' },
                    { name: 'Private (Dan only)', value: 'private' }
                )
        )
].map(command => command.toJSON());

const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

(async () => {
    try {
        console.log('Started refreshing application (/) commands.');

        // Register commands globally (takes up to 1 hour to propagate)
        // If you want instant updates, use guild commands instead
        await rest.put(
            Routes.applicationCommands(process.env.DISCORD_CLIENT_ID),
            { body: commands },
        );

        console.log('✅ Successfully registered /selfie command!');
        console.log('Note: Global commands may take up to 1 hour to appear.');
    } catch (error) {
        console.error('❌ Error registering commands:', error);
    }
})();
