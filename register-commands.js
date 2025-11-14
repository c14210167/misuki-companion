// Register slash commands with Discord
// Run this once: node register-commands.js

require('dotenv').config();
const { REST, Routes, SlashCommandBuilder } = require('discord.js');

const commands = [
    new SlashCommandBuilder()
        .setName('selfie')
        .setDescription('Admin: Control selfie settings')
        .addSubcommand(subcommand =>
            subcommand
                .setName('mode')
                .setDescription('Change selfie permission mode')
                .addStringOption(option =>
                    option.setName('mode')
                        .setDescription('Selfie permission mode')
                        .setRequired(true)
                        .addChoices(
                            { name: 'All (everyone can request)', value: 'all' },
                            { name: 'Private (Dan only)', value: 'private' }
                        )
                )
        )
        .addSubcommand(subcommand =>
            subcommand
                .setName('forcesend')
                .setDescription('Force Misuki to send a selfie immediately')
                .addStringOption(option =>
                    option.setName('prompt')
                        .setDescription('Additional prompt details (e.g., "sucking finger", "blushing", etc.)')
                        .setRequired(false)
                )
                .addBooleanOption(option =>
                    option.setName('unaware')
                        .setDescription('If true, selfie will NOT be saved to database (she won\'t remember sending it)')
                        .setRequired(false)
                )
        ),
    new SlashCommandBuilder()
        .setName('imagine')
        .setDescription('Admin: Generate a candid image of what Misuki is doing right now')
        .addStringOption(option =>
            option.setName('prompt')
                .setDescription('Additional prompt details (optional)')
                .setRequired(false)
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

        console.log('✅ Successfully registered /selfie and /imagine commands!');
        console.log('Note: Global commands may take up to 1 hour to appear.');
    } catch (error) {
        console.error('❌ Error registering commands:', error);
    }
})();
